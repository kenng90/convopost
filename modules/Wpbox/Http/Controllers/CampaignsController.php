<?php

namespace Modules\Wpbox\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Contacts\Models\Field;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Jobs\SendMessage;
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

        if ($this->getCompany()->getConfig('whatsapp_webhook_verified', 'no') != 'yes' || $this->getCompany()->getConfig('whatsapp_settings_done', 'no') != 'yes') {
            return redirect(route('whatsapp.setup'));
        }

        $items = $this->provider::with('template')
            ->orderBy('id', 'desc')
            ->whereNull('contact_id')
            ->where('is_bot', false)
            ->where('is_api', false)
            ->where('is_reminder', false);
        if (isset($_GET['name']) && strlen($_GET['name']) > 1) {
            $items = $items->where('name', 'like', '%'.$_GET['name'].'%');
        }
        $items = $items->paginate(100);

        return view($this->view_path.'index', ['total_contacts' => Contact::count(),
            'setup' => [

                'title' => __('crud.item_managment', ['item' => __($this->titlePlural)]),
                'iscontent' => true,
                'action_link' => route($this->webroute_path.'create'),
                'action_name' => __('Send new campaign').' 📢',
                'action_link2' => route('wpbox.api.index', ['type' => 'api']),
                'action_name2' => __('Manage API campaigns'),
                'items' => $items,
                'item_names' => $this->titlePlural,
                'webroute_path' => $this->webroute_path,
                'fields' => [],
                'custom_table' => true,
                'parameter_name' => $this->parameter_name,
                'parameters' => count($_GET) != 0,
            ]]);
    }

    public function show(Campaign $campaign)
    {

        //Get countries we have send to
        $contact_ids = $campaign->messages()->select(['contact_id'])->pluck('contact_id')->toArray();
        $countriesCount = DB::table('contacts')
            ->join('countries', 'contacts.country_id', '=', 'countries.id')
            ->selectRaw('count(contacts.id) as number_of_messages, country_id, countries.name, countries.lat, countries.lng')
            ->whereIn('contacts.id', $contact_ids)
            ->groupBy('contacts.country_id')
            ->get()->toArray();

        $dataToSend = [
            'total_contacts' => Contact::count(),
            'item' => $campaign,
            'setup' => [
                'countriesCount' => $countriesCount,
                'title' => __('Campaign').' '.$campaign->name,
                'action_link' => route($this->webroute_path.'index'),
                'action_name' => '📢 '.__('Back'),
                'items' => $campaign->messages()->with('contact')->paginate(config('settings.paginate')),
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
            $dataToSend['setup']['action_name'] = __('Back to Api');
            $dataToSend['setup']['action_link'] = route('wpbox.api.index', ['type' => 'api']);
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

    private function componentToVariablesList($template)
    {
        $jsonData = json_decode($template->components, true);

        $variables = [];
        foreach ($jsonData as $item) {

            if ($item['type'] == 'HEADER' && $item['format'] == 'TEXT') {
                preg_match_all('/{{(\d+)}}/', $item['text'], $matches);
                if (! empty($matches[1])) {
                    foreach ($matches[1] as $id) {
                        $exampleValue = '';
                        try {
                            $exampleValue = $item['example']['header_text'][$id - 1];
                        } catch (\Throwable $th) {
                        }
                        $variables['header'][] = ['id' => $id, 'exampleValue' => $exampleValue];
                    }
                }
            } elseif ($item['type'] == 'HEADER' && $item['format'] == 'DOCUMENT') {
                $variables['document'] = true;
            } elseif ($item['type'] == 'HEADER' && $item['format'] == 'IMAGE') {
                $variables['image'] = true;
            } elseif ($item['type'] == 'HEADER' && $item['format'] == 'VIDEO') {
                $variables['video'] = true;
            } elseif ($item['type'] == 'BODY') {
                preg_match_all('/{{(\d+)}}/', $item['text'], $matches);
                if (! empty($matches[1])) {
                    foreach ($matches[1] as $id) {
                        $exampleValue = '';
                        try {
                            $exampleValue = $item['example']['body_text'][0][$id - 1];
                        } catch (\Throwable $th) {
                        }
                        $variables['body'][] = ['id' => $id, 'exampleValue' => $exampleValue];
                    }
                }
            } elseif ($item['type'] == 'BUTTONS') {
                foreach ($item['buttons'] as $keyBtn => $button) {
                    if ($button['type'] == 'URL') {
                        preg_match_all('/{{(\d+)}}/', $button['url'], $matches);

                        if (! empty($matches[1])) {

                            foreach ($matches[1] as $id) {
                                $exampleValue = '';
                                try {
                                    $exampleValue = $button['url'];
                                    $exampleValue = str_replace('{{1}}', '', $exampleValue);
                                } catch (\Throwable $th) {
                                }
                                $variables['buttons'][$id - 1][] = ['id' => $id, 'exampleValue' => $exampleValue, 'type' => $button['type'], 'text' => $button['text']];
                            }
                        }
                    }
                    if ($button['type'] == 'COPY_CODE') {
                        $exampleValue = $button['example'][0];
                        $variables['buttons'][$keyBtn][] = ['id' => $keyBtn, 'exampleValue' => $exampleValue, 'type' => $button['type'], 'text' => $button['text']];
                    }

                }

            }
        }

        return $variables;
    }

    public function create(Request $request, $type = null)
    {
        $templates = [];
        foreach (Template::where('status', 'APPROVED')->get() as $key => $template) {
            $templates[$template->id] = $template->name.' - '.$template->language;
        }
        if (count($templates) == 0) {
            //If there are 0 template,re-load them
            try {
                $this->loadTemplatesFromWhatsApp();
                foreach (Template::where('status', 'APPROVED')->get() as $key => $template) {
                    $templates[$template->id] = $template->name.' - '.$template->language;
                }
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        if (count($templates) == 0) {
            //Redirect to templates
            return redirect()->route('templates.index')->withStatus(__('Please add a template first. Or wait some to be approved'));
        }

        $groups = Group::pluck('name', 'id');
        $groups[0] = __('Send to all contacts');

        $selectedTemplate = null;
        $variables = null;
        if (isset($_GET['template_id'])) {
            $selectedTemplate = Template::withoutGlobalScope(\App\Scopes\CompanyScope::class)->where('id', $_GET['template_id'])->first();
            $variables = $this->componentToVariablesList($selectedTemplate);

        }

        $isApiCampaignMaker = $request->has('type') && $request->type === 'api';
        $isReminderCampaignMaker = $request->has('type') && $request->type === 'reminder';

        $contactFields = [];
        if ($isApiCampaignMaker) {
            $contactFields[-3] = __('Use API defined value');
        }

        if ($isReminderCampaignMaker) {
            //Add Start date, Start time, Start Date And time, End date, End time and End date and time
            $contactFields[-4] = __('Start date');
            $contactFields[-5] = __('Start time');
            $contactFields[-6] = __('Start date and time');
            $contactFields[-7] = __('End date');
            $contactFields[-8] = __('End time');
            $contactFields[-9] = __('End date and time');
            $contactFields[-10] = __('External ID');
        }

        $contactFields[-2] = __('Use manually defined value');
        $contactFields[-1] = __('Contact name');
        $contactFields[0] = __('Contact phone');
        foreach (Field::pluck('name', 'id') as $key => $value) {
            $contactFields[$key] = $value;
        }

        $selectedContacts = 0;
        if (isset($_GET['group_id'])) {
            if ($_GET['group_id'] == '0') {
                $selectedContacts = Contact::where('subscribed', 1)->count();
            } else {
                $group = Group::findOrFail($_GET['group_id']);
                $selectedContacts = $group->contacts()->where('subscribed', 1)->count();
            }
        }

        $dataToSend = [
            'selectedContacts' => $selectedContacts,
            'selectedTemplate' => $selectedTemplate,
            'selectedTemplateComponents' => $selectedTemplate ? json_decode($selectedTemplate->components, true) : null,
            'contactFields' => $contactFields,
            'variables' => $variables,
            'groups' => $groups,
            'contacts' => Contact::pluck('name', 'id'),
            'templates' => $templates,
            'isBot' => $request->has('type') && $request->type === 'bot',
            'isAPI' => $isApiCampaignMaker,
            'isReminder' => $isReminderCampaignMaker,
        ];

        if ($isReminderCampaignMaker) {
            $dataToSend['sources'] = \Modules\Reminders\Models\Source::pluck('name', 'id');
            //Prepend the all source
            $dataToSend['sources'] = collect([0 => __('All')])->union($dataToSend['sources']);
        }

        // If type is specified → show specific form
        if (in_array($type, ['file', 'group', 'quick'])) {
            return view($this->view_path.'create_'.$type, $dataToSend);
        }

        return view($this->view_path.'create', $dataToSend);
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

        if ($request->input('broadcast_type') === 'file') {
            return $this->storeFileBroadcast($request);
        }

        if ($request->input('broadcast_type') === 'quick') {
            return $this->storeQuickBroadcast($request);
        }

        $campaign = $this->provider::create([
            'name' => $request->has('name') ? $request->name : 'template_message_'.now(),
            'timestamp_for_delivery' => $request->has('send_now') ? null : $request->send_time,
            'variables' => $request->has('paramvalues') ? json_encode($request->paramvalues) : '',
            'variables_match' => json_encode($request->parammatch),
            'template_id' => $request->template_id,
            'group_id' => $request->group_id.'' === '0' ? null : $request->group_id,
            'contact_id' => $request->contact_id,
            'total_contacts' => Contact::count(),
            'broadcast_type' => 'group',
        ]);

        $isBot = $request->has('type') && $request->type === 'bot';

        if ($isBot) {
            $campaign->is_bot = true;
            $campaign->bot_type = $request->reply_type;
            $campaign->trigger = $request->trigger;
            $campaign->save();
        }

        $isAPI = $request->has('type') && $request->type === 'api';

        if ($isAPI) {
            $campaign->is_api = true;
            $campaign->save();
        }

        $isReminder = $request->has('type') && $request->type === 'reminder';

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

        if ($isAPI) {
            return redirect()->route('wpbox.api.index', ['type' => 'api'])->withStatus(__('You have created new API Campaigns.'));
        }

        if ($isReminder) {
            return redirect()->route('reminders.reminders.index')->withStatus(__('You have created a new reminder.'));
        }

        $campaign->makeMessages($request);

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

    public function sendSchuduledMessages()
    {
        //Find all unsent Messages that are within the timeline
        $limit = 100;

        //campaign_sending_batch
        try {
            $limit = (int) config('wpbox.campaign_sending_batch', 100);

            //Limit must be number
            if (! is_numeric($limit)) {
                $limit = 100;
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
        $messagesToBeSend = Message::where('status', 0)
            ->where('scchuduled_at', '<', Carbon::now())
            ->whereIn('campaign_id', function ($query) {
                $query->select('id')
                    ->from('wa_campaings')
                    ->where('is_active', true);
            })
            ->limit($limit)
            ->get();
        foreach ($messagesToBeSend as $key => $message) {
            if (config('wpbox.campaign_sending_type', 'normal') == 'normal') {
                //Old way - send all at once
                $this->sendCampaignMessageToWhatsApp($message);
            } else {
                dispatch(new SendMessage($message));
            }
        }

    }

    //Delete campaign, only if type is BOT
    public function destroy(Campaign $campaign)
    {
        if ($campaign->is_bot || $campaign->is_api) {
            $campaign->delete();
            //Redirect based on campaign type
            if ($campaign->is_api) {
                return redirect()->route('wpbox.api.index', ['type' => 'api'])->withStatus(__('API Campaign deleted'));
            }

            return redirect()->route('replies.index', ['type' => 'bot'])->withStatus(__('Bot deleted'));
        } else {
            return redirect()->route($this->webroute_path.'index')->withStatus(__('You can only delete bot campaigns'));
        }
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
        $filename = 'report_campaign_'.$campaign->id.'_'.now().'.csv';
        $handle = fopen($filename, 'w+');
        fputcsv($handle, ['Name', 'Phone', 'Country', 'Status', 'Sent at', 'Last status update', 'Extra']);
        foreach ($campaign->messages as $key => $message) {
            //Status
            $status = '';
            $error = $message->error;
            if ($message->status == 0) {
                $status = 'PENDING_SENT';
            } elseif ($message->status == 1 || $message->status = 2) {
                $status = 'SENT';
            } elseif ($message->status == 3) {
                $status = 'DELIVERED';
            } elseif ($message->status == 4) {
                $status = 'READ';
            } elseif ($message->status == 5) {
                $status = 'FAILED';
            }
            try {
                fputcsv($handle, [$message->contact->name, $message->contact->phone, $message->contact->country->name, $status, $message->scchuduled_at ? $message->scchuduled_at : $message->created_at, $message->updated_at, $error]);
            } catch (\Throwable $th) {
                //throw $th;
            }

        }
        fclose($handle);
        $headers = [
            'Content-Type' => 'text/csv',
        ];

        return response()->download($filename, $filename, $headers)->deleteFileAfterSend(true);
    }
}
