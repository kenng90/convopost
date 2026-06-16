<?php

namespace Modules\Journies\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Journies\Models\Journey;
use Modules\Journies\Models\JourneyActivity;
use Modules\Journies\Services\JourneyAnalyticsService;
use Modules\Journies\Services\JourneyContactService;
use Modules\Journies\Services\JourneySettings;
use Modules\Journies\Services\JourneyTemplateService;
use Modules\Wpbox\Models\Contact as WpboxContact;

class Main extends Controller
{
    private $provider = Journey::class;

    private $webroute_path = 'journies.';

    private $view_path = 'journies::';

    private $parameter_name = 'journey';

    private $title = 'journey';

    private $titlePlural = 'journeys';

    public function __construct(
        private JourneyContactService $journeyContacts,
        private JourneyTemplateService $templates,
        private JourneyAnalyticsService $analytics,
    ) {
    }

    private function getFields($class = 'col-md-4')
    {
        return [
            ['class' => $class, 'ftype' => 'input', 'name' => 'Name', 'id' => 'name', 'placeholder' => __('Enter name'), 'required' => true],
            ['class' => $class, 'ftype' => 'textarea', 'name' => 'Description', 'id' => 'description', 'placeholder' => __('Enter description')],
        ];
    }

    private function authChecker(): void
    {
        $this->ownerAndStaffOnly();

        $user = auth()->user();
        $company = $user?->currentCompany();

        if ($user && $user->hasRole('staff') && $company && ! JourneySettings::for($company)->staffCanManage()) {
            abort(403, __('Staff users cannot manage journeys for this workspace.'));
        }
    }

    public function index()
    {
        $this->authChecker();

        $items = $this->provider::query()
            ->withCount('stages')
            ->orderBy('id', 'desc')
            ->paginate(config('settings.paginate'));

        $items->getCollection()->transform(function (Journey $journey) {
            $journey->contacts_count = $journey->contactsCount();

            return $journey;
        });

        return view($this->view_path.'index', [
            'setup' => [
                'usefilter' => null,
                'title' => __('Journeys'),
                'subtitle' => __('Build pipelines, automate WhatsApp messages per stage, and track contacts from the inbox sidebar.'),
                'action_link2' => route($this->webroute_path.'create'),
                'action_name2' => __('Create journey'),
                'action_link3' => route($this->webroute_path.'analytics'),
                'action_name3' => __('Analytics'),
                'items' => $items,
                'item_names' => $this->titlePlural,
                'webroute_path' => $this->webroute_path,
                'fields' => $this->getFields('col-md-3'),
                'filterFields' => $this->getFields('col-md-3'),
                'custom_table' => true,
                'parameter_name' => $this->parameter_name,
                'parameters' => count($_GET) != 0,
                'hidePaging' => false,
            ],
            'templates' => $this->templates->templates(),
        ]);
    }

    public function create()
    {
        $this->authChecker();

        return view('general.form', [
            'setup' => [
                'title' => __('crud.new_item', ['item' => __('Journey')]),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('crud.back'),
                'iscontent' => true,
                'action' => route($this->webroute_path.'store'),
            ],
            'fields' => $this->getFields('col-md-6'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authChecker();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $this->provider::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->route($this->webroute_path.'index')
            ->withStatus(__('crud.item_has_been_added', ['item' => __('Journey')]));
    }

    public function createFromTemplate(string $template)
    {
        $this->authChecker();

        $journey = $this->templates->createFromTemplate($template);

        if (! $journey) {
            return redirect()->route($this->webroute_path.'index')
                ->withError(__('Template not found.'));
        }

        return redirect()->route('journies.kanban', $journey)
            ->withStatus(__('Journey created from template. Link API campaigns to each stage when you are ready.'));
    }

    public function edit($id)
    {
        $this->authChecker();
        $journey = $this->provider::findOrFail($id);

        $fields = $this->getFields('col-md-6');
        $fields[0]['value'] = $journey->name;
        $fields[1]['value'] = $journey->description;

        return view('general.form', [
            'setup' => [
                'title' => __('crud.edit_item_name', ['item' => __('Journey'), 'name' => $journey->name]),
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => __('crud.back'),
                'iscontent' => true,
                'isupdate' => true,
                'action' => route($this->webroute_path.'update', ['journey' => $journey->id]),
            ],
            'fields' => $fields,
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->authChecker();

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $item = $this->provider::findOrFail($id);
        $item->update($request->only('name', 'description'));

        return redirect()->route($this->webroute_path.'index')
            ->withStatus(__('crud.item_has_been_updated', ['item' => __('Journey')]));
    }

    public function destroy(Request $request, $id)
    {
        $this->authChecker();

        $item = $this->provider::findOrFail($id);
        $item->delete();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route($this->webroute_path.'index')
            ->withStatus(__('crud.item_has_been_removed', ['item' => __('Journey')]));
    }

    public function kanban(Journey $journey)
    {
        $this->authChecker();

        $journey->load(['stages' => function ($query) {
            $query->with(['contacts', 'campaign:id,name'])->withCount('contacts');
        }]);

        if ($journey->stages->isEmpty()) {
            return redirect()->route('stages.create', $journey)
                ->withStatus(__('Add the first stage to start the journey'));
        }

        $existingContactIds = $journey->stages
            ->flatMap(fn ($stage) => $stage->contacts->pluck('id'))
            ->unique()
            ->values();

        return view('journies::kanban', [
            'journey' => $journey,
            'existingContactIds' => $existingContactIds,
            'confirmBeforeSend' => JourneySettings::for(auth()->user()?->currentCompany())->confirmBeforeSend(),
        ]);
    }

    public function addContact(Journey $journey, Request $request)
    {
        $this->authChecker();

        $request->validate(['contact_id' => 'required|integer|exists:contacts,id']);

        $stage = $journey->stages()->orderBy('order')->orderBy('id')->first();
        $contact = WpboxContact::findOrFail($request->contact_id);

        $result = $this->journeyContacts->moveContactToStage(
            $contact,
            $stage,
            'manual_kanban',
            auth()->id(),
            $request->boolean('fire_campaign', true),
        );

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return redirect()->route('journies.kanban', $journey)->withStatus($result['message']);
    }

    public function searchContacts(Request $request, Journey $journey): JsonResponse
    {
        $this->authChecker();

        $query = trim((string) $request->get('q', ''));

        $existingIds = $journey->stages()
            ->with('contacts:id')
            ->get()
            ->flatMap(fn ($stage) => $stage->contacts->pluck('id'))
            ->unique();

        $contacts = WpboxContact::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', '%'.$query.'%')
                        ->orWhere('phone', 'like', '%'.$query.'%');
                });
            })
            ->when($request->boolean('exclude_existing', true), fn ($builder) => $builder->whereNotIn('id', $existingIds))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'avatar']);

        return response()->json(['data' => $contacts]);
    }

    public function getJournies(WpboxContact $contact): JsonResponse
    {
        $company = auth()->user()?->currentCompany();

        if (! $company || ! $company->hasPlanPlugin('journies')) {
            return response()->json([
                'journeys' => [],
                'activities' => [],
            ]);
        }

        $confirmBeforeSend = JourneySettings::for($company)->confirmBeforeSend();

        $journeys = Journey::with(['stages' => function ($query) {
            $query->select('id', 'journey_id', 'name', 'order', 'campaign_id')
                ->with('campaign:id,name')
                ->orderBy('order')
                ->orderBy('id');
        }])->select('id', 'name', 'description')->get()->map(function (Journey $journey) use ($contact, $confirmBeforeSend) {
            $currentStage = $this->journeyContacts->currentStageForContact($contact, $journey);

            $journey->current_stage_id = $currentStage?->id;
            $journey->current_stage_name = $currentStage?->name;
            $journey->in_journey = $currentStage !== null;
            $journey->confirm_before_send = $confirmBeforeSend;

            $journey->stages->transform(function ($stage) use ($currentStage) {
                $stage->contact_in = $currentStage && (int) $currentStage->id === (int) $stage->id;
                $stage->campaign_name = $stage->campaign?->name;

                return $stage;
            });

            return $journey;
        });

        $activities = [];

        try {
            $activities = JourneyActivity::query()
                ->where('contact_id', $contact->id)
                ->with(['stage:id,name', 'journey:id,name', 'user:id,name'])
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (JourneyActivity $activity) => [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'journey_name' => $activity->journey?->name,
                    'stage_name' => $activity->stage?->name,
                    'user_name' => $activity->user?->name,
                    'source' => $activity->source,
                    'campaign_status' => $activity->campaign_status,
                    'created_at' => $activity->created_at?->diffForHumans(),
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'journeys' => $journeys,
            'activities' => $activities,
        ]);
    }

    public function removeContact(Journey $journey, WpboxContact $contact): JsonResponse
    {
        $this->authChecker();

        $company = auth()->user()?->currentCompany();
        if (! $company || ! $company->hasPlanPlugin('journies')) {
            return response()->json(['success' => false, 'message' => __('Journeys are not available on your plan.')], 403);
        }

        $result = $this->journeyContacts->removeContactFromJourney($contact, $journey, 'manual_sidebar', auth()->id());

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function analytics(Request $request)
    {
        $this->authChecker();

        $journeyId = $request->integer('journey_id') ?: null;
        $journeys = Journey::query()->orderBy('name')->get(['id', 'name']);

        return view('journies::analytics', [
            'journeys' => $journeys,
            'selectedJourneyId' => $journeyId,
        ]);
    }

    public function analyticsData(Request $request): JsonResponse
    {
        $this->authChecker();

        $journeyId = $request->integer('journey_id') ?: null;

        return response()->json([
            'status' => 'success',
            'data' => $this->analytics->summary($journeyId),
            'conversion' => $journeyId ? $this->analytics->conversionRates($journeyId) : [],
        ]);
    }
}
