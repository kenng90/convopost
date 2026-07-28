<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Campaign\ApiCampaignService;
use App\Services\Campaign\CampaignDispatchService;
use App\Services\Campaign\CampaignEstimateService;
use App\Services\Campaign\CampaignShowPresenter;
use App\Services\Campaign\CampaignTemplateVariablesParser;
use App\Services\Telephony\Sms\SmsAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Models\Field;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Message;
use Modules\Wpbox\Models\Template;
use Modules\Wpbox\Traits\Whatsapp;

class CampaignsController extends Controller
{
    use Whatsapp;

    /**
     * Provide class.
     */
    private $provider = Campaign::class;

    /**
     * Web RoutePath for the name of the routes.
     */
    private $webroute_path = 'campaigns.';

    /**
     * View path.
     */
    private $view_path = 'wpbox::campaigns.';

    /**
     * Parameter name.
     */
    private $parameter_name = 'campaigns';

    /**
     * Title of this crud.
     */
    private $title = 'campaign';

    /**
     * Title of this crud in plural.
     */
    private $titlePlural = 'campaigns';

    public function index()
    {

        $this->authChecker();

        $company = $this->getCompany();
        $activeChannel = request('channel', Campaign::CHANNEL_WHATSAPP);

        if (! array_key_exists($activeChannel, Campaign::broadcastChannelLabels())) {
            $activeChannel = Campaign::CHANNEL_WHATSAPP;
        }

        $whatsappReady = $company->getConfig('whatsapp_webhook_verified', 'no') == 'yes'
            && $company->getConfig('whatsapp_settings_done', 'no') == 'yes';

        $smsReady = app(SmsAvailability::class)->isReady($company);

        $items = $this->provider::with(['template', 'company'])
            ->broadcastsOnly()
            ->forChannel($activeChannel)
            ->orderBy('id', 'desc');

        if (request()->filled('name') && strlen((string) request('name')) > 1) {
            $items = $items->where('name', 'like', '%'.request('name').'%');
        }

        if (request()->filled('status')) {
            $items = $items->where('status', request('status'));
        }

        if (request()->filled('broadcast_type')) {
            $items = $items->where('broadcast_type', request('broadcast_type'));
        }

        $items = $items->paginate(100)->appends(request()->query());

        $channelCounts = [];
        foreach (array_keys(Campaign::broadcastChannelLabels()) as $channel) {
            $channelCounts[$channel] = Campaign::broadcastsOnly()->forChannel($channel)->count();
        }

        return view($this->view_path.'index', [
            'total_contacts' => Contact::count(),
            'whatsappReady' => $whatsappReady,
            'smsReady' => $smsReady,
            'activeChannel' => $activeChannel,
            'channelCounts' => $channelCounts,
            'channelLabels' => Campaign::broadcastChannelLabels(),
            'dispatcherLastRun' => cache('campaign_dispatcher_last_run'),
            'setup' => [

                'title' => __('crud.item_managment', ['item' => __($this->titlePlural)]),
                'iscontent' => true,
                'action_link' => route($this->webroute_path.'wizard', ['channel' => $activeChannel]),
                'action_name' => __('Send new campaign').' 📢',
                'action_link2' => route('campaigns.integrations'),
                'action_name2' => __('Integrations hub'),
                'action_link3' => route('wpbox.api.index', ['type' => 'api']),
                'action_name3' => __('Manage API campaigns'),
                'items' => $items,
                'item_names' => $this->titlePlural,
                'webroute_path' => $this->webroute_path,
                'fields' => [],
                'custom_table' => true,
                'parameter_name' => $this->parameter_name,
                'parameters' => request()->query() !== [],
            ]]);
    }

    public function show(Campaign $campaign)
    {
        $campaign->load(['template', 'segment', 'company']);

        $presenter = CampaignShowPresenter::for($campaign);

        //Get countries we have send to
        $contact_ids = $campaign->messages()->select(['contact_id'])->pluck('contact_id')->toArray();
        $countriesCount = DB::table('contacts')
            ->join('countries', 'contacts.country_id', '=', 'countries.id')
            ->selectRaw('count(contacts.id) as number_of_messages, country_id, countries.name, countries.lat, countries.lng')
            ->whereIn('contacts.id', $contact_ids)
            ->groupBy('contacts.country_id')
            ->get()->toArray();

        $analytics = $presenter->analytics();

        $dataToSend = [
            'presenter' => $presenter,
            'contentPreview' => $presenter->contentPreview(),
            'total_contacts' => Contact::where('company_id', $campaign->company_id)->count(),
            'analytics' => $analytics,
            'item' => $campaign,
            'setup' => [
                'countriesCount' => $countriesCount,
                'title' => __('Campaign').' '.$campaign->name,
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => '📢 '.__('Back'),
                'items' => $campaign->messages()->with('contact.country')->paginate(config('settings.paginate')),
                'item_names' => $this->titlePlural,
                'webroute_path' => $this->webroute_path,
                'fields' => [],
                'custom_table' => true,
                'parameter_name' => $this->parameter_name,
                'parameters' => count($_GET) != 0,
            ]];

        if ($campaign->is_bot) {
            $dataToSend['setup']['title'] = __('Bot').' '.$campaign->name;
            $dataToSend['setup']['action_name'] = __('Back to bots').' 🤖';
            $dataToSend['setup']['action_link'] = route('replies.index', ['type' => 'bot']);
        } elseif ($campaign->is_api) {
            $dataToSend['setup']['title'] = __('API').' '.$campaign->name;
            $dataToSend['setup']['action_name'] = __('Back to API campaigns');
            $dataToSend['setup']['action_link'] = route('wpbox.api.index');
            $dataToSend['setup']['action_link2'] = route('wpbox.api.edit', $campaign);
            $dataToSend['setup']['action_name2'] = '✏️ '.__('Edit');
            $dataToSend['setup']['action_link3'] = route('wpbox.api.clone', $campaign);
            $dataToSend['setup']['action_name3'] = '📋 '.__('Clone');
            $dataToSend['setup']['action_link4'] = route('wpbox.api.toggle', $campaign);
            $dataToSend['setup']['action_name4'] = $campaign->is_active && $campaign->status !== Campaign::STATUS_INACTIVE
                ? '⏸️ '.__('Deactivate')
                : '▶️ '.__('Activate');
            $dataToSend['apiToken'] = $this->getCompany()?->getConfig('plain_token', '');
        } else {
            //Regular campaign
            //If there is at lease 1 pending message, show action to pause campaign
            $pendingMessages = $campaign->messages()->where('status', 0)->count();
            if ($pendingMessages > 0 && $campaign->is_active) {
                $dataToSend['setup']['action_link2'] = route($this->webroute_path.'pause', $campaign->id);
                $dataToSend['setup']['action_name2'] = '⏸️ '.__('Pause campaign');
            } elseif ($pendingMessages > 0) {
                $dataToSend['setup']['action_link2'] = route($this->webroute_path.'resume', $campaign->id);
                $dataToSend['setup']['action_name2'] = '▶️ '.__('Resume campaign');
            }

            $dataToSend['setup']['action_link3'] = route($this->webroute_path.'report', $campaign->id);
            $dataToSend['setup']['action_name3'] = '📊 '.__('Download report');

            if (in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED, Campaign::STATUS_SENDING], true)) {
                $dataToSend['setup']['action_link4'] = route($this->webroute_path.'cancel', $campaign->id);
                $dataToSend['setup']['action_name4'] = '⛔ '.__('Cancel campaign');
            }

            $dataToSend['setup']['action_link5'] = route($this->webroute_path.'clone', $campaign->id);
            $dataToSend['setup']['action_name5'] = '📋 '.__('Clone campaign');
        }

        return view($this->view_path.'show', $dataToSend);
    }

    /**
     * Auth checker function for the crud.
     */
    private function authChecker()
    {
        $this->ownerAndStaffOnly();
    }

    public function componentToVariablesListPublic(Template $template): array
    {
        return app(CampaignTemplateVariablesParser::class)->parse($template);
    }

    private function componentToVariablesList($template)
    {
        return app(CampaignTemplateVariablesParser::class)->parse($template);
    }

    public function create(Request $request, $type = null)
    {
        $specialType = $this->resolveSpecialCampaignType($request, $type);

        if ($specialType === 'api') {
            return redirect()->route('wpbox.api.create', $request->query());
        }

        if ($specialType === null) {
            return redirect()->route('campaigns.wizard', array_filter([
                'broadcast_type' => in_array($type, ['file', 'group', 'quick'], true) ? $type : 'group',
                'channel' => $request->query('channel'),
                'contact_id' => $request->query('contact_id'),
                'template_id' => $request->query('template_id'),
                'group_id' => $request->query('group_id'),
                'send_now' => $request->has('send_now') ? 1 : null,
            ], fn ($value) => $value !== null && $value !== ''));
        }

        return $this->renderSpecialCampaignForm($request, $specialType);
    }

    /**
     * Resolve bot/api/reminder from path or query (path wins when both present).
     */
    private function resolveSpecialCampaignType(Request $request, ?string $type = null): ?string
    {
        foreach ([$type, $request->query('type')] as $candidate) {
            if (in_array($candidate, ['bot', 'api', 'reminder'], true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    private function renderSpecialCampaignForm(Request $request, string $specialType)
    {
        $templates = [];
        foreach (Template::where('status', 'APPROVED')->get() as $template) {
            $templates[$template->id] = $template->name.' - '.$template->language;
        }

        if (count($templates) == 0) {
            try {
                $this->loadTemplatesFromWhatsApp();
                foreach (Template::where('status', 'APPROVED')->get() as $template) {
                    $templates[$template->id] = $template->name.' - '.$template->language;
                }
            } catch (\Throwable $th) {
            }
        }

        if (count($templates) == 0) {
            return redirect()->route('templates.index')->withStatus(__('Please add a template first. Or wait some to be approved'));
        }

        $selectedTemplate = null;
        $variables = null;
        if ($request->filled('template_id')) {
            $selectedTemplate = Template::withoutGlobalScope(\App\Scopes\CompanyScope::class)
                ->where('id', $request->template_id)
                ->first();
            if ($selectedTemplate) {
                $variables = $this->componentToVariablesList($selectedTemplate);
            }
        }

        $isBot = $specialType === 'bot';
        $isApi = $specialType === 'api';
        $isReminder = $specialType === 'reminder';

        $contactFields = [];
        if ($isApi) {
            $contactFields[-3] = __('Use API defined value');
        }

        if ($isReminder) {
            foreach (\Modules\Reminders\Services\BookingMessageContextService::campaignFieldOptions() as $id => $label) {
                $contactFields[$id] = $label;
            }
        }

        $contactFields[-2] = __('Use manually defined value');
        $contactFields[-1] = __('Contact name');
        $contactFields[0] = __('Contact phone');
        foreach (Field::pluck('name', 'id') as $key => $value) {
            $contactFields[$key] = $value;
        }

        $dataToSend = [
            'selectedContacts' => 0,
            'selectedTemplate' => $selectedTemplate,
            'selectedTemplateComponents' => $selectedTemplate ? json_decode($selectedTemplate->components, true) : null,
            'contactFields' => $contactFields,
            'variables' => $variables,
            'groups' => collect([0 => __('Send to all contacts')])->union(Group::pluck('name', 'id')),
            'contacts' => Contact::pluck('name', 'id'),
            'templates' => $templates,
            'isBot' => $isBot,
            'isAPI' => $isApi,
            'isReminder' => $isReminder,
            'specialType' => $specialType,
            'formAction' => route('campaigns.store'),
            'campaign' => null,
        ];

        if ($isReminder) {
            $dataToSend['sources'] = collect([0 => __('All')])->union(
                \Modules\Reminders\Models\Source::pluck('name', 'id')
            );
        }

        return view($this->view_path.'create_group', $dataToSend);
    }

    public function parseFile(Request $request)
    {
        $request->validate([
            'contact_file' => 'required|file|mimes:csv,xlsx,xls,txt',
        ]);

        $file = $request->file('contact_file');
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'csv' || $ext === 'txt') {
            $data = $this->parseCsvFile($file->getRealPath());
        } else {
            $data = $this->parseXlsxFile($file->getRealPath());
        }

        return response()->json($data);
    }

    // ── private helpers ───────────────────────────────────────────────────

    private function parseCsvFile(string $path): array
    {
        $rows = [];
        $headers = [];

        if (($handle = fopen($path, 'r')) !== false) {
            $first = true;
            while (($row = fgetcsv($handle)) !== false) {
                if ($first) {
                    $headers = array_map('trim', $row);
                    $first = false;
                } else {
                    $rows[] = $row;
                }
            }
            fclose($handle);
        }

        return [
            'headers' => $headers,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    private function parseXlsxFile(string $path): array
    {
        // Requires phpoffice/phpspreadsheet
        // composer require phpoffice/phpspreadsheet
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        $headers = array_map('trim', (array) array_shift($data));
        $rows = array_values($data);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'row_count' => count($rows),
        ];
    }

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION B — store() additions
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Add this block at the TOP of store(), before the existing
     *   $campaign = $this->provider::create([...]);
     *
     * It handles broadcast_type=file entirely and returns early.
     */

    // ┌─ paste from here ──────────────────────────────────────────────────────
    // public function store(Request $request)
    // {
    //     // ── FILE BROADCAST ────────────────────────────────────────────────────
    //     if ($request->input('broadcast_type') === 'file') {
    //         return $this->storeFileBroadcast($request);
    //     }

    //     // ── everything below is the ORIGINAL store() body — unchanged ─────────

    //     $campaign = $this->provider::create([
    //         'name'                 => $request->has('name') ? $request->name : 'template_message_' . now(),
    //         'timestamp_for_delivery' => $request->has('send_now') ? null : $request->send_time,
    //         'variables'            => $request->has('paramvalues') ? json_encode($request->paramvalues) : '',
    //         'variables_match'      => json_encode($request->parammatch),
    //         'template_id'          => $request->template_id,
    //         'group_id'             => $request->group_id . '' === '0' ? null : $request->group_id,
    //         'contact_id'           => $request->contact_id,
    //         'total_contacts'       => Contact::count(),
    //     ]);

    //     $isBot = $request->has('type') && $request->type === 'bot';
    //     if ($isBot) {
    //         $campaign->is_bot    = true;
    //         $campaign->bot_type  = $request->reply_type;
    //         $campaign->trigger   = $request->trigger;
    //         $campaign->save();
    //     }

    //     $isAPI = $request->has('type') && $request->type === 'api';
    //     if ($isAPI) {
    //         $campaign->is_api = true;
    //         $campaign->save();
    //     }

    //     $isReminder = $request->has('type') && $request->type === 'reminder';
    //     if ($isReminder) {
    //         $campaign->is_reminder = true;
    //         $campaign->save();

    //         $reminder = \Modules\Reminders\Models\Remineder::create([
    //             'campaign_id' => $campaign->id,
    //             'name'        => $request->has('name') ? $request->name : 'template_message_' . now(),
    //             'source_id'   => $request->source_id == 0 ? null : $request->source_id,
    //             'type'        => $request->reminder_type,
    //             'time'        => $request->reminder_time,
    //             'time_type'   => $request->reminder_unit,
    //             'status'      => 1,
    //         ]);
    //     }

    //     if ($request->hasFile('pdf')) {
    //         $campaign->media_link = $this->saveDocument('', $request->pdf);
    //         $campaign->update();
    //     }
    //     if ($request->hasFile('imageupload')) {
    //         $campaign->media_link = $this->saveDocument('', $request->imageupload);
    //         $campaign->update();
    //     }

    //     if ($isBot) {
    //         return redirect()->route('replies.index', ['type' => 'bot'])->withStatus(__('You have created a new bot.'));
    //     } elseif ($isAPI) {
    //         return redirect()->route('wpbox.api.index', ['type' => 'api'])->withStatus(__('You have created new API Campaigns.'));
    //     } elseif ($isReminder) {
    //         return redirect()->route('reminders.reminders.index')->withStatus(__('You have created a new reminder.'));
    //     } else {
    //         $campaign->makeMessages($request);
    //         if ($request->has('contact_id')) {
    //             return redirect()->route('chat.index')->withStatus(__('Message will be send shortly. Please note that if new contact, it will not appear in this list until the contact start interacting with you!'));
    //         } else {
    //             return redirect()->route($this->webroute_path . 'index')->withStatus(__('Campaign is ready to be send'));
    //         }
    //     }
    // }
    // └─ end of new store() ───────────────────────────────────────────────────

    // ════════════════════════════════════════════════════════════════════════════
    // SECTION C — storeFileBroadcast()   (new private method)
    // ════════════════════════════════════════════════════════════════════════════

    /**
     * Handle the file-broadcast form submission.
     *
     * Flow:
     *  1. Parse the uploaded file → get headers + rows.
     *  2. Create the Campaign record (no group_id, file-based).
     *  3. For every data row:
     *     a. Resolve the phone number from the chosen column.
     *     b. Find-or-create the Contact.
     *     c. Build the per-row paramvalues by merging:
     *           static values (from form)  ←  overridden by file column value
     *        according to the file_column_map submitted by the view.
     *     d. Create a Message record (mirrors what Campaign::makeMessages() does
     *        for regular broadcasts — adapt the field names to your Message model).
     */
    private function storeFileBroadcast(Request $request)
    {
        $this->authChecker();

        $request->validate([
            'contact_file' => 'required|file|mimes:csv,xlsx,xls,txt',
            'template_id' => 'required',
            'phone_column' => 'required|string',
        ]);

        try {
            $file = $request->file('contact_file');
            $ext = strtolower($file->getClientOriginalExtension());
            $data = $ext === 'csv' || $ext === 'txt'
                ? $this->parseCsvFile($file->getRealPath())
                : $this->parseXlsxFile($file->getRealPath());

            $headers = $data['headers'];
            $rows = $data['rows'];
            $phoneColumn = $request->input('phone_column');
            $phoneIndex = $this->resolvePhoneColumnIndex($headers, $phoneColumn);

            if ($phoneIndex === false) {
                return back()->withInput()->withErrors([
                    'phone_column' => __('Selected phone column not found in file.'),
                ]);
            }

            $fileColumnMap = $request->input('file_column_map', []);
            $staticParamValues = $request->input('paramvalues', []);
            $parammatch = $this->buildFileBroadcastParamMatch(
                $request->input('parammatch', []),
                $fileColumnMap
            );

            $validRowCount = $this->countValidFileBroadcastRows($rows, $headers, $phoneIndex);

            if ($validRowCount === 0) {
                return back()->withInput()->withErrors([
                    'contact_file' => __('No valid contacts found in file. Check the phone column and number format.'),
                ]);
            }

            $campaign = $this->provider::create([
                'name' => $request->has('name') ? $request->name : 'file_broadcast_'.now(),
                'timestamp_for_delivery' => $request->has('send_now') ? null : $request->send_time,
                'variables' => json_encode($staticParamValues),
                'variables_match' => json_encode($parammatch),
                'template_id' => $request->template_id,
                'group_id' => null,
                'contact_id' => null,
                'broadcast_type' => 'file',
                'total_contacts' => 0,
                'send_to' => 0,
            ]);

            $this->attachCampaignMedia($campaign, $request);

            $messages = [];
            $demoLimit = config('settings.is_demo', false) ? 5 : null;

            foreach ($rows as $row) {
                if (empty(array_filter($row))) {
                    continue;
                }

                while (count($row) < count($headers)) {
                    $row[] = '';
                }

                $phone = $this->normalizePhoneFromFileCell($row[$phoneIndex] ?? null);

                if ($phone === null) {
                    continue;
                }

                $contact = Contact::firstOrCreate(
                    ['phone' => $phone],
                    ['name' => $phone, 'subscribed' => 1]
                );

                $perRowParams = $this->applyFileColumnMapToParamValues(
                    $staticParamValues,
                    $fileColumnMap,
                    $headers,
                    $row
                );

                if ($demoLimit !== null && count($messages) >= $demoLimit) {
                    break;
                }

                $messageData = $campaign->buildMessageDataForContact($contact, $request, $perRowParams);

                if ($messageData !== null) {
                    $messages[] = $messageData;
                }
            }

            if (count($messages) === 0) {
                $campaign->delete();

                return back()->withInput()->withErrors([
                    'contact_file' => __('Could not build messages for this template. Check variable mappings and try again.'),
                ]);
            }

            $campaign->insertCampaignMessages($messages);
            $campaign->send_to = count($messages);
            $campaign->total_contacts = count($messages);
            $campaign->save();

            return redirect()
                ->route($this->webroute_path.'show', ['campaign' => $campaign->id])
                ->withStatus(__('File broadcast queued for :count contacts.', ['count' => count($messages)]));
        } catch (\Throwable $exception) {
            Log::error('File broadcast failed', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors([
                'contact_file' => __('Could not create the campaign.').' '.$exception->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        $this->authChecker();

        if ($request->input('type') === 'api') {
            $campaign = app(ApiCampaignService::class)->create(
                $this->getCompany(),
                app(ApiCampaignService::class)->payloadFromRequest($request)
            );

            return redirect()
                ->route('campaigns.show', $campaign)
                ->withStatus(__('API campaign created. Use campaign ID :id to trigger it.', ['id' => $campaign->id]));
        }

        if ($request->input('broadcast_type') === 'file') {
            return $this->storeFileBroadcast($request);
        }

        if ($request->input('broadcast_type') === 'quick') {
            return $this->storeQuickBroadcast($request);
        }

        $isBot = $request->input('type') === 'bot';
        $isReminder = $request->input('type') === 'reminder';

        $campaign = $this->provider::create([
            'name' => $request->has('name') ? $request->name : 'template_message_'.now(),
            'timestamp_for_delivery' => ($isBot || $isReminder || $request->has('send_now')) ? null : $request->send_time,
            'variables' => $request->has('paramvalues') ? json_encode($request->paramvalues) : '',
            'variables_match' => json_encode($request->parammatch),
            'template_id' => $request->template_id,
            'group_id' => ($isBot || $isReminder || $request->group_id.'' === '0') ? null : $request->group_id,
            'segment_id' => ($isBot || $isReminder) ? null : ($request->segment_id ?: null),
            'contact_id' => ($isBot || $isReminder) ? null : $request->contact_id,
            'total_contacts' => ($isBot || $isReminder) ? 0 : Contact::count(),
            'broadcast_type' => ($isBot || $isReminder) ? null : 'group',
            'channel' => $request->input('channel', Campaign::CHANNEL_WHATSAPP),
            'timezone_mode' => $request->input('timezone_mode', Campaign::TIMEZONE_MODE_CONTACT),
            'status' => ($isBot || $isReminder)
                ? Campaign::STATUS_ACTIVE
                : ($request->boolean('save_draft') ? Campaign::STATUS_DRAFT : Campaign::STATUS_SCHEDULED),
            'is_active' => true,
        ]);

        if ($isBot) {
            $campaign->is_bot = true;
            $campaign->bot_type = $request->reply_type;
            $campaign->trigger = $request->trigger;
            $campaign->save();
        }

        if ($isReminder) {
            $campaign->is_reminder = true;
            $campaign->save();

            \Modules\Reminders\Models\Remineder::create([
                'campaign_id' => $campaign->id,
                'name' => $request->has('name') ? $request->name : 'template_message_'.now(),
                'source_id' => $request->source_id == 0 ? null : $request->source_id,
                'type' => $request->reminder_type,
                'time' => $request->reminder_time,
                'time_type' => $request->reminder_unit,
                'status' => 1,
            ]);
        }

        $this->attachCampaignMedia($campaign, $request);

        if ($isBot) {
            return redirect()->route('replies.index', ['type' => 'bot'])->withStatus(__('You have created a new bot.'));
        }

        if ($isReminder) {
            return redirect()->route('reminders.reminders.index')->withStatus(__('You have created a new reminder.'));
        }

        if ($request->boolean('save_draft')) {
            return redirect()->route($this->webroute_path.'show', $campaign)->withStatus(__('Campaign saved as draft.'));
        }

        $template = Template::find($request->template_id);
        $estimate = app(CampaignEstimateService::class)->estimate(
            $this->getCompany(),
            $template,
            [
                'group_id' => $campaign->group_id,
                'segment_id' => $campaign->segment_id,
                'contact_id' => $campaign->contact_id,
            ]
        );

        if (! $estimate['can_afford'] && config('settings.enable_credits', false)) {
            $campaign->delete();

            return back()->withInput()->withErrors([
                'credits' => __('Insufficient credits. This campaign requires :credits credits.', ['credits' => $estimate['total_credits']]),
            ]);
        }

        $campaign->makeMessages($request);
        $campaign->update([
            'status' => Campaign::STATUS_SENDING,
            'launched_at' => now(),
        ]);

        if ($request->has('send_now')) {
            app(CampaignDispatchService::class)->dispatchPendingBatch();
        }

        if ($request->has('contact_id')) {
            return redirect()->route('chat.index')->withStatus(__('Message will be send shortly. Please note that if new contact, it will not appear in this list until the contact start interacting with you!'));
        }

        return redirect()->route($this->webroute_path.'index')->withStatus(__('Campaign is ready to be send'));
    }

    private function storeQuickBroadcast(Request $request)
    {
        $request->validate([
            'template_id' => 'required|exists:wa_templates,id',
            'quick_phones' => 'required|string',
        ]);

        $rawPhones = $request->input('quick_phones', '');
        $phones = collect(preg_split('/[\n,]+/', $rawPhones))
            ->map(fn ($p) => $this->normalizePhoneFromFileCell($p))
            ->filter()
            ->unique()
            ->values();

        if ($phones->isEmpty()) {
            return back()->withInput()->withErrors(['quick_phones' => __('No valid phone numbers found.')]);
        }

        $campaign = $this->provider::create([
            'name' => $request->has('name') && $request->name
                ? $request->name
                : 'quick_broadcast_'.now(),
            'timestamp_for_delivery' => $request->has('send_now') ? null : $request->send_time,
            'variables' => $request->has('paramvalues')
                ? json_encode($request->paramvalues)
                : '',
            'variables_match' => json_encode($request->input('parammatch', [])),
            'template_id' => $request->template_id,
            'group_id' => null,
            'contact_id' => null,
            'broadcast_type' => 'quick',
            'total_contacts' => 0,
            'send_to' => 0,
        ]);

        $this->attachCampaignMedia($campaign, $request);

        $contacts = $phones->map(function ($phone) {
            return Contact::firstOrCreate(
                ['phone' => $phone],
                ['name' => $phone, 'subscribed' => 1]
            );
        });

        $queued = $campaign->queueMessagesForContacts($request, $contacts);

        if ($queued === 0) {
            $campaign->delete();

            return back()->withInput()->withErrors(['quick_phones' => __('No valid phone numbers found.')]);
        }

        return redirect()
            ->route($this->webroute_path.'show', ['campaign' => $campaign->id])
            ->withStatus(__('Quick broadcast queued for :count contacts.', ['count' => $queued]));
    }

    /**
     * @param  array<string, mixed>  $requestParammatch
     * @param  array<string, array<string, string>>  $fileColumnMap
     * @return array<string, mixed>
     */
    private function buildFileBroadcastParamMatch(array $requestParammatch, array $fileColumnMap): array
    {
        $parammatch = $requestParammatch;

        foreach (['body', 'header'] as $section) {
            if (! isset($fileColumnMap[$section])) {
                continue;
            }

            foreach ($fileColumnMap[$section] as $variableId => $colName) {
                if (! empty($colName)) {
                    $parammatch[$section][$variableId] = '-2';
                }
            }
        }

        return $parammatch;
    }

    private function countValidFileBroadcastRows(array $rows, array $headers, int $phoneIndex): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            while (count($row) < count($headers)) {
                $row[] = '';
            }

            if ($this->normalizePhoneFromFileCell($row[$phoneIndex] ?? null) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function attachCampaignMedia(Campaign $campaign, Request $request): void
    {
        if ($request->hasFile('pdf')) {
            $campaign->media_link = $this->saveDocument('', $request->pdf);
            $campaign->save();
        }

        if ($request->hasFile('imageupload')) {
            $campaign->media_link = $this->saveDocument('', $request->imageupload);
            $campaign->save();
        }
    }

    private function resolvePhoneColumnIndex(array $headers, string $phoneColumn): int|false
    {
        $phoneIndex = array_search($phoneColumn, $headers, true);

        if ($phoneIndex !== false) {
            return $phoneIndex;
        }

        foreach ($headers as $index => $header) {
            if (strcasecmp(trim((string) $header), trim($phoneColumn)) === 0) {
                return $index;
            }
        }

        return false;
    }

    private function normalizePhoneFromFileCell(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            $value = number_format((float) $value, 0, '', '');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $value);

        return strlen($phone) >= 7 ? $phone : null;
    }

    /**
     * @param  array<string, mixed>  $staticParamValues
     * @param  array<string, array<string, string>>  $fileColumnMap
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>
     */
    private function applyFileColumnMapToParamValues(array $staticParamValues, array $fileColumnMap, array $headers, array $row): array
    {
        $perRowParams = json_decode(json_encode($staticParamValues), true) ?? [];

        foreach (['body', 'header'] as $section) {
            if (! isset($fileColumnMap[$section])) {
                continue;
            }

            foreach ($fileColumnMap[$section] as $variableId => $colName) {
                if (empty($colName)) {
                    continue;
                }

                $colIdx = array_search($colName, $headers, true);

                if ($colIdx === false) {
                    foreach ($headers as $index => $header) {
                        if (strcasecmp(trim((string) $header), trim((string) $colName)) === 0) {
                            $colIdx = $index;
                            break;
                        }
                    }
                }

                if ($colIdx === false) {
                    continue;
                }

                $cellValue = $row[$colIdx] ?? '';

                if (is_int($cellValue) || is_float($cellValue)) {
                    $cellValue = number_format((float) $cellValue, 0, '', '');
                }

                $perRowParams[$section][$variableId] = trim((string) $cellValue);
            }
        }

        return $perRowParams;
    }

    public function sendSchuduledMessages(CampaignDispatchService $dispatchService)
    {
        $sent = $dispatchService->dispatchPendingBatch();

        return response()->json(['status' => 'ok', 'sent' => $sent]);
    }

    public function wizard()
    {
        $this->authChecker();

        return view($this->view_path.'wizard');
    }

    public function estimate(Request $request)
    {
        $this->authChecker();

        $request->validate([
            'template_id' => 'required|integer',
            'group_id' => 'nullable',
            'segment_id' => 'nullable|integer',
        ]);

        $template = Template::findOrFail($request->template_id);
        $estimate = app(CampaignEstimateService::class)->estimate(
            $this->getCompany(),
            $template,
            $request->only(['group_id', 'segment_id', 'contact_id'])
        );

        return response()->json($estimate);
    }

    public function cloneCampaign(Campaign $campaign)
    {
        $this->authChecker();

        if ($campaign->is_api) {
            return redirect()->route('wpbox.api.clone', $campaign);
        }

        if (! $campaign->isBroadcast()) {
            return redirect()->back()->withStatus(__('Only broadcast campaigns can be cloned.'));
        }

        $clone = $campaign->cloneAsDraft();

        return redirect()->route($this->webroute_path.'wizard', ['draft' => $clone->id])
            ->withStatus(__('Campaign cloned. Review and launch when ready.'));
    }

    public function cancel(Campaign $campaign)
    {
        $this->authChecker();

        $campaign->cancelPendingMessages();
        $campaign->update([
            'status' => Campaign::STATUS_CANCELLED,
            'is_active' => false,
        ]);

        return redirect()->route($this->webroute_path.'show', $campaign)->withStatus(__('Campaign cancelled.'));
    }

    public function launch(Campaign $campaign)
    {
        $this->authChecker();

        if ($campaign->status !== Campaign::STATUS_DRAFT) {
            return redirect()->back()->withStatus(__('Only draft campaigns can be launched.'));
        }

        $request = new Request(['send_now' => 'on']);
        $campaign->makeMessages($request);
        $campaign->update([
            'status' => Campaign::STATUS_SENDING,
            'launched_at' => now(),
            'is_active' => true,
        ]);

        app(CampaignDispatchService::class)->dispatchPendingBatch();

        return redirect()->route($this->webroute_path.'show', $campaign)->withStatus(__('Campaign launched.'));
    }

    //Delete campaign
    public function destroy(Campaign $campaign)
    {
        if ($campaign->is_bot || $campaign->is_api) {
            $campaign->delete();
            //Redirect based on campaign type
            if ($campaign->is_api) {
                return redirect()->route('wpbox.api.index', ['type' => 'api'])->withStatus(__('API Campaign deleted'));
            }

            return redirect()->route('replies.index', ['type' => 'bot'])->withStatus(__('Bot deleted'));
        }

        if ($campaign->isBroadcast() && in_array($campaign->status, [Campaign::STATUS_DRAFT, Campaign::STATUS_CANCELLED, Campaign::STATUS_COMPLETED], true)) {
            $campaign->delete();

            return redirect()->route($this->webroute_path.'index')->withStatus(__('Campaign deleted'));
        }

        return redirect()->route($this->webroute_path.'index')->withStatus(__('You can only delete draft, cancelled, or completed broadcast campaigns'));
    }

    //Activate bot
    public function activateBot(Campaign $campaign)
    {
        $campaign->is_bot_active = true;
        $campaign->save();

        return redirect()->route('replies.index', ['type' => 'bot'])->withStatus(__('Bot activated'));
    }

    //Deactivate bot
    public function deactivateBot(Campaign $campaign)
    {
        $campaign->is_bot_active = false;
        $campaign->save();

        return redirect()->route('replies.index', ['type' => 'bot'])->withStatus(__('Bot deactivated'));
    }

    //Pause campaign
    public function pause(Campaign $campaign)
    {
        $campaign->is_active = false;
        $campaign->save();

        return redirect()->route($this->webroute_path.'show', $campaign)->withStatus(__('Campaign paused'));
    }

    //Resume campaign
    public function resume(Campaign $campaign)
    {
        $campaign->is_active = true;
        $campaign->save();

        return redirect()->route($this->webroute_path.'show', $campaign)->withStatus(__('Campaign resumed'));
    }

    //Download report
    public function report(Campaign $campaign)
    {
        $presenter = CampaignShowPresenter::for($campaign);
        $filename = 'report_campaign_'.$campaign->id.'_'.now()->format('Y-m-d_His').'.csv';
        $handle = fopen($filename, 'w+');
        fputcsv($handle, $presenter->reportHeaders());

        foreach ($campaign->messages()->with('contact.country')->get() as $message) {
            try {
                fputcsv($handle, $presenter->reportRow($message));
            } catch (\Throwable $th) {
            }
        }

        fclose($handle);
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        return response()->download($filename, $filename, $headers)->deleteFileAfterSend(true);
    }
}
