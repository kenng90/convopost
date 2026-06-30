<?php

namespace Modules\Flowmaker\Http\Controllers;

use App\Models\Company;
use App\Services\Flowmaker\BookingFlowAnalyticsService;
use App\Services\Flowmaker\BookingFlowHealthService;
use App\Services\Flowmaker\FlowHealthValidator;
use App\Services\Flowmaker\FlowTemplateService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Contacts\Models\Field;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Models\FlowRunLog;
use Modules\Wpbox\Models\Template;

class Main extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function edit(Flow $flow)
    {
        $customFields = Field::where('company_id', $flow->company_id)->get();

        $variables = [
            ['label' => 'Contact Name', 'value' => 'contact_name', 'category' => 'Contact'],
            ['label' => 'Contact Phone', 'value' => 'contact_phone', 'category' => 'Contact'],
            ['label' => 'Email', 'value' => 'contact_email', 'category' => 'Contact'],
            ['label' => 'Country', 'value' => 'contact_country', 'category' => 'Contact'],
            ['label' => 'Last Message', 'value' => 'contact_last_message', 'category' => 'Contact'],
        ];

        foreach ($customFields as $customField) {
            $variables[] = [
                'label' => $customField->name,
                'value' => $customField->name,
                'category' => 'Custom Field',
            ];
        }

        $template = app(FlowTemplateService::class)->get($flow->source_template ?? '');
        $checklist = $template['post_install_checklist'] ?? [];

        $data = [
            'flow' => $flow->only(['id', 'name', 'flow_data', 'draft_flow_data', 'has_unpublished_changes', 'company_id', 'updated_at', 'source_template']),
            'variables' => $variables,
            'post_install_checklist' => $checklist,
        ];

        return view('flowmaker::index')->with('data', json_encode($data));
    }

    public function editorMetadata(Flow $flow)
    {
        $templates = Template::where('company_id', $flow->company_id)->get(['id', 'name', 'language', 'components']);
        foreach ($templates as $template) {
            $template->components = json_decode($template->components);
        }

        $agents = \App\Models\User::role('staff')->where('company_id', $flow->company_id)->get(['id', 'name', 'email']);
        $groups = \Modules\Contacts\Models\Group::where('company_id', $flow->company_id)->get(['id', 'name']);

        $journeys = [];
        if (class_exists(\Modules\Journies\Models\Journey::class)) {
            $journeys = \Modules\Journies\Models\Journey::where('company_id', $flow->company_id)
                ->with(['stages' => fn ($query) => $query->orderBy('order')->orderBy('id')->select('id', 'journey_id', 'name', 'order')])
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        $company = auth()->user()->currentCompany();

        return response()->json([
            'templates' => $templates,
            'agents' => $agents,
            'groups' => $groups,
            'journeys' => $journeys,
            'planPlugins' => [
                'whatsappflows' => $company ? $company->hasPlanPlugin('whatsappflows') : false,
                'whatsappcatalog' => $company ? $company->hasPlanPlugin('whatsappcatalog') : false,
                'reminders' => $company ? $company->hasPlanPlugin('reminders') : false,
            ],
            'bookingSetupUrls' => [
                'overview' => route('reminders.overview.index'),
                'services' => route('reminders.sources.index'),
                'events' => route('reminders.events.index'),
                'settings' => route('reminders.booking-settings.index'),
            ],
        ]);
    }

    public function bookingServices()
    {
        $company = auth()->user()?->currentCompany();

        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company not found'], 403);
        }

        return response()->json([
            'success' => true,
            'services' => app(\Modules\Reminders\Services\BookingCatalogService::class)
                ->bookableServicesForCompany($company),
        ]);
    }

    public function bookingEvents()
    {
        $company = auth()->user()?->currentCompany();

        if (! $company) {
            return response()->json(['success' => false, 'message' => 'Company not found'], 403);
        }

        $catalog = app(\Modules\Reminders\Services\EventCatalogService::class);

        return response()->json([
            'success' => true,
            'events_enabled' => $catalog->eventsEnabled($company),
            'occurrences' => $catalog->upcomingOccurrencesForCompany($company),
        ]);
    }

    public function script()
    {
        // Find the first .js file in the public/build/assets directory
        $files = glob(__DIR__.'/../../public/build/assets/*.js');

        if (empty($files)) {
            abort(404, 'JavaScript file not found');
        }

        try {
            $script = file_get_contents($files[0]);

            return response($script)->header('Content-Type', 'application/javascript');
        } catch (\Exception $e) {
            abort(500, 'Error loading JavaScript file');
        }
    }

    //CSS
    public function css()
    {
        $files = glob(__DIR__.'/../../public/build/assets/*.css');

        if (empty($files)) {
            abort(404, 'CSS file not found');
        }

        try {
            $css = file_get_contents($files[0]);

            return response($css)->header('Content-Type', 'text/css');
        } catch (\Exception $e) {
            abort(500, 'Error loading CSS file');
        }
    }

    public function updateFlow(Request $request, Flow $flow)
    {
        $payload = $request->all();
        $health = $this->validateFlowHealth($payload, $flow);

        $flow->draft_flow_data = json_encode($payload);
        $flow->has_unpublished_changes = true;
        $flow->save();

        return response()->json([
            'status' => 'ok',
            'health' => $health,
            'has_unpublished_changes' => true,
        ]);
    }

    public function publishFlow(Request $request, Flow $flow)
    {
        if ($request->has('nodes')) {
            $flow->draft_flow_data = json_encode($request->all());
        }

        $draft = $flow->draft_flow_data ?: $flow->flow_data;
        if (! $draft) {
            return response()->json(['status' => 'error', 'message' => 'No draft to publish.'], 422);
        }

        $payload = is_string($draft) ? json_decode($draft, true) : $draft;
        $health = $this->validateFlowHealth($payload ?? [], $flow);

        if (! $health['valid']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Fix flow errors before publishing.',
                'health' => $health,
            ], 422);
        }

        $flow->flow_data = is_string($draft) ? $draft : json_encode($draft);
        $flow->has_unpublished_changes = false;
        $flow->save();

        return response()->json([
            'status' => 'ok',
            'health' => $health,
            'has_unpublished_changes' => false,
        ]);
    }

    public function validateFlow(Request $request, Flow $flow)
    {
        $payload = $request->all();
        if (empty($payload['nodes'])) {
            $editorData = $flow->draft_flow_data ?: $flow->flow_data;
            $payload = json_decode($editorData ?? '{}', true) ?? [];
        }

        $health = $this->validateFlowHealth($payload, $flow);

        return response()->json(['health' => $health]);
    }

    public function bookingAnalytics(Flow $flow)
    {
        $days = (int) request('days', 30);

        return response()->json([
            'success' => true,
            'analytics' => app(BookingFlowAnalyticsService::class)->summaryForFlow($flow->id, max(1, min($days, 90))),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    private function validateFlowHealth(array $payload, Flow $flow): array
    {
        $health = (new FlowHealthValidator)->validate($payload);

        $company = auth()->user()?->currentCompany() ?? Company::find($flow->company_id);

        if ($company) {
            $bookingWarnings = app(BookingFlowHealthService::class)->validateForCompany($company, $payload);
            $health['warnings'] = array_values(array_unique(array_merge($health['warnings'], $bookingWarnings)));
        }

        return $health;
    }

    public function simulateFlow(Request $request, Flow $flow)
    {
        $message = (string) $request->input('message', '');
        $editorData = $flow->draft_flow_data ?: $flow->flow_data;
        $payload = json_decode($editorData ?? '{}', true) ?? [];
        $health = (new FlowHealthValidator)->validate($payload);

        $matchedKeywords = [];
        foreach ($payload['nodes'] ?? [] as $node) {
            if (($node['type'] ?? '') !== 'keyword_trigger') {
                continue;
            }

            foreach ($node['data']['keywords'] ?? $node['data']['settings']['keywords'] ?? [] as $keyword) {
                $value = strtolower((string) ($keyword['value'] ?? ''));
                $matchType = $keyword['matchType'] ?? 'contains';
                $haystack = strtolower($message);

                $matches = $matchType === 'exact'
                    ? $haystack === $value
                    : str_contains($haystack, $value);

                if ($matches && $value !== '') {
                    $matchedKeywords[] = $keyword['value'];
                }
            }
        }

        return response()->json([
            'health' => $health,
            'simulation' => [
                'message' => $message,
                'matched_keywords' => array_values(array_unique($matchedKeywords)),
                'would_start' => ! empty($matchedKeywords) || collect($payload['nodes'] ?? [])->contains(fn ($n) => in_array($n['type'] ?? '', ['incomingMessage', 'incoming_message'], true)),
            ],
        ]);
    }

    public function flowRunLogs(Flow $flow)
    {
        $logs = FlowRunLog::query()
            ->where('flow_id', $flow->id)
            ->latest()
            ->limit(100)
            ->get(['id', 'contact_id', 'node_id', 'event', 'detail', 'created_at']);

        return response()->json(['logs' => $logs]);
    }

    /**
     * Upload media files (images, videos, PDFs)
     *
     * @return Response
     */
    public function uploadMedia(Request $request)
    {
        try {
            $type = $request->input('type');
            Log::info('Upload media', ['type' => $type]);
            // Validate request
            $request->validate([
                'file' => 'required|file|max:50000', // Max 50MB
                'type' => 'required|in:image,video,pdf,document',
            ]);

            // Get the file and type
            $file = $request->file('file');

            // Set validation rules based on type
            switch ($type) {
                case 'image':
                    $request->validate([
                        'file' => 'mimes:jpeg,png,jpg,gif,webp|max:10000', // Max 10MB for images
                    ]);
                    $directory = 'flowmaker/images';
                    break;
                case 'video':
                    $request->validate([
                        'file' => 'mimes:mp4,webm,ogg,avi,mov|max:50000', // Max 50MB for videos
                    ]);
                    $directory = 'flowmaker/videos';
                    break;
                case 'pdf':
                case 'document':
                    $request->validate([
                        'file' => 'mimes:pdf,txt,docx,doc|max:20000', // Max 20MB for PDFs and TXT files
                    ]);
                    $directory = 'flowmaker/documents';
                    break;
                default:
                    return response()->json(['error' => 'Invalid file type'], 400);
            }

            // Generate unique filename
            $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();

            // Store the file
            $laravel_file_resource = $file;
            //$path = $file->storeAs($directory, $fileName, 'public');

            if (config('settings.use_s3_as_storage', false)) {
                //S3 - store per company
                $path = $laravel_file_resource->storePubliclyAs('uploads/companies', $fileName, 's3');

                $full_url = config('filesystems.disks.s3.url').'/'.$path;
            } else {
                $path = $laravel_file_resource->store($directory, 'public_uploads');
                $url = config('app.url').'/uploads/'.$path;

                $full_url = preg_replace('#(https?:\/\/[^\/]+)\/\/#', '$1/', $url);
            }

            // Return the media URL
            return response()->json([
                'status' => 'success',
                'url' => $full_url,
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
