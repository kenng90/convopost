<?php

namespace App\Livewire;

use App\Services\Campaign\CampaignDispatchService;
use App\Services\Campaign\CampaignEstimateService;
use App\Services\Campaign\CampaignFileParser;
use App\Services\Campaign\CampaignLaunchService;
use App\Services\Campaign\CampaignTemplateVariablesParser;
use App\Services\Campaign\Templates\CampaignTemplateResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;
use Modules\Contacts\Models\Field;
use Modules\Contacts\Models\Group;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\CampaignSegment;
use Modules\Wpbox\Models\Template;

class CampaignWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public int $totalSteps = 5;

    public string $name = '';

    public string $channel = Campaign::CHANNEL_WHATSAPP;

    public string $broadcastType = 'group';

    public ?int $templateId = null;

    public ?string $channelTemplateKey = null;

    public string $smsBody = '';

    public string $emailSubject = '';

    public string $emailBody = '';

    public ?int $groupId = null;

    public ?int $segmentId = null;

    public ?int $contactId = null;

    public string $quickPhones = '';

    public $contactFile = null;

    /** @var array<int, string> */
    public array $fileHeaders = [];

    public int $fileRowCount = 0;

    public string $recipientColumn = '';

    /** @var array<string, array<string, string>> */
    public array $fileColumnMap = [];

    public string $timezoneMode = Campaign::TIMEZONE_MODE_CONTACT;

    public ?string $sendTime = null;

    public bool $sendNow = true;

    public string $recurrenceInterval = '';

    public int $recurrenceCount = 0;

    /** @var array<string, mixed> */
    public array $paramvalues = [];

    /** @var array<string, mixed> */
    public array $parammatch = [];

    public ?int $draftId = null;

    public ?string $abVariant = null;

    public $pdf = null;

    public $imageupload = null;

    /** @var array<string, mixed> */
    public array $estimate = [];

    public function mount(): void
    {
        $this->channel = request()->query('channel', Campaign::CHANNEL_WHATSAPP);
        $this->broadcastType = request()->query('broadcast_type', 'group');
        $this->contactId = request()->integer('contact_id') ?: null;
        $this->templateId = request()->integer('template_id') ?: null;
        $this->sendNow = request()->has('send_now');
        $this->groupId = request()->integer('group_id') ?: null;

        $draft = request()->integer('draft');
        if ($draft) {
            $campaign = Campaign::find($draft);
            if ($campaign) {
                $this->loadFromCampaign($campaign);
            }
        }

        if ($this->channelTemplateKey === null) {
            $this->resetChannelTemplateKey();
        }
    }

    public function updatedChannel(): void
    {
        $this->templateId = null;
        $this->channelTemplateKey = null;
        $this->smsBody = '';
        $this->emailSubject = '';
        $this->emailBody = '';
        $this->paramvalues = [];
        $this->parammatch = [];
        $this->resetChannelTemplateKey();

        if ($this->channel === Campaign::CHANNEL_EMAIL && $this->broadcastType === 'quick') {
            $this->broadcastType = 'group';
        }
    }

    public function updatedBroadcastType(): void
    {
        if ($this->channel === Campaign::CHANNEL_EMAIL && $this->broadcastType === 'quick') {
            $this->broadcastType = 'group';
        }
    }

    public function updatedContactFile(): void
    {
        $this->validate(['contactFile' => 'file|mimes:csv,xlsx,xls,txt|max:10240']);

        $parser = app(CampaignFileParser::class);
        $data = $parser->parseFromPath(
            $this->contactFile->getRealPath(),
            $this->contactFile->getClientOriginalExtension()
        );

        $this->fileHeaders = $data['headers'];
        $this->fileRowCount = $data['row_count'];
        $this->recipientColumn = $this->guessRecipientColumn($data['headers']);
    }

    public function updated($property): void
    {
        if (in_array($property, ['templateId', 'groupId', 'segmentId', 'channel', 'broadcastType', 'quickPhones', 'recipientColumn', 'smsBody', 'emailBody'], true)) {
            $this->refreshEstimate();
        }

        if ($property === 'channelTemplateKey') {
            $this->loadChannelTemplateDefaults();
            $this->refreshEstimate();
        }
    }

    public function nextStep(): void
    {
        $this->validateStep($this->step);
        $this->step = min($this->totalSteps, $this->step + 1);

        if ($this->step >= $this->totalSteps - 1) {
            $this->refreshEstimate();
        }
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function saveDraft(): void
    {
        try {
            $campaign = $this->launch(draft: true);
            session()->flash('status', __('Campaign saved as draft.'));
            $this->redirect(route('campaigns.show', $campaign));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function launchCampaign(): void
    {
        try {
            $campaign = $this->launch(draft: false);

            app(CampaignDispatchService::class)->enqueuePendingBatch();

            $message = $campaign->status === Campaign::STATUS_PREPARING
                ? __('Campaign is being prepared. Messages will send once preparation completes.')
                : __('Campaign launched successfully.');

            session()->flash('status', $message);
            $this->redirect(route('campaigns.show', $campaign));
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    private function launch(bool $draft): Campaign
    {
        $this->validateStep($this->totalSteps);

        return app(CampaignLaunchService::class)->launch(
            Auth::user()->currentCompany(),
            $this->launchPayload(),
            $draft
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function launchPayload(): array
    {
        $recurrence = null;
        $recurrenceNext = null;

        if ($this->recurrenceInterval !== '') {
            $recurrence = [
                'interval' => $this->recurrenceInterval,
                'count' => $this->recurrenceCount,
                'runs_completed' => 0,
            ];
            $recurrenceNext = now()->add(match ($this->recurrenceInterval) {
                'daily' => '1 day',
                'monthly' => '1 month',
                default => '1 week',
            });
        }

        return [
            'draft_id' => $this->draftId,
            'name' => $this->name,
            'channel' => $this->channel,
            'broadcast_type' => $this->broadcastType,
            'template_id' => $this->templateId,
            'channel_template_key' => $this->channelTemplateKey,
            'sms_body' => $this->smsBody,
            'email_subject' => $this->emailSubject,
            'email_body' => $this->emailBody,
            'group_id' => $this->groupId,
            'segment_id' => $this->segmentId,
            'contact_id' => $this->contactId,
            'quick_phones' => $this->quickPhones,
            'contact_file' => $this->contactFile,
            'recipient_column' => $this->recipientColumn,
            'phone_column' => $this->recipientColumn,
            'file_column_map' => $this->fileColumnMap,
            'paramvalues' => $this->paramvalues,
            'parammatch' => $this->parammatch,
            'send_now' => $this->sendNow,
            'send_time' => $this->sendTime,
            'timezone_mode' => $this->timezoneMode,
            'ab_variant' => $this->abVariant,
            'recurrence_rule' => $recurrence,
            'recurrence_next_at' => $recurrenceNext,
            'pdf' => $this->pdf,
            'imageupload' => $this->imageupload,
        ];
    }

    private function validateStep(int $step): void
    {
        for ($i = 1; $i <= min($step, 3); $i++) {
            $this->validateStepRules($i);
        }
    }

    private function validateStepRules(int $step): void
    {
        if ($step === 1) {
            $this->validate([
                'name' => 'required|string|max:120',
                'channel' => 'required|in:whatsapp,sms,email',
                'broadcastType' => 'required|in:group,file,quick',
            ]);
        }

        if ($step === 2) {
            if ($this->broadcastType === 'group') {
                $this->validate(['groupId' => 'nullable']);
            } elseif ($this->broadcastType === 'quick') {
                $this->validate(['quickPhones' => 'required|string']);
            } else {
                $this->validate([
                    'contactFile' => 'required|file|mimes:csv,xlsx,xls,txt',
                    'recipientColumn' => 'required|string',
                ]);
            }
        }

        if ($step === 3) {
            if ($this->channel === Campaign::CHANNEL_WHATSAPP) {
                $this->validate(['templateId' => 'required|integer']);
            } elseif ($this->channel === Campaign::CHANNEL_SMS) {
                if ($this->channelTemplateKey === 'custom') {
                    $this->validate(['smsBody' => 'required|string|min:1']);
                }
            } elseif ($this->channel === Campaign::CHANNEL_EMAIL) {
                if (empty($this->channelTemplateKey)) {
                    $this->validate([
                        'emailSubject' => 'required|string|min:1',
                        'emailBody' => 'required|string|min:1',
                    ]);
                }
            }
        }
    }

    private function loadFromCampaign(Campaign $campaign): void
    {
        $vars = json_decode($campaign->variables, true) ?? [];

        $this->draftId = $campaign->id;
        $this->name = $campaign->name;
        $this->channel = $campaign->channel ?? Campaign::CHANNEL_WHATSAPP;
        $this->broadcastType = $campaign->broadcast_type ?? 'group';
        $this->templateId = $campaign->template_id;
        $this->channelTemplateKey = $campaign->channel_template_key;
        $this->groupId = $campaign->group_id ?? 0;
        $this->segmentId = $campaign->segment_id;
        $this->contactId = $campaign->contact_id;
        $this->timezoneMode = $campaign->timezone_mode ?? Campaign::TIMEZONE_MODE_CONTACT;
        $this->paramvalues = $vars;
        $this->parammatch = json_decode($campaign->variables_match, true) ?? [];
        $this->sendTime = $campaign->timestamp_for_delivery;
        $this->abVariant = $campaign->ab_variant;
        $this->smsBody = $vars['sms_body'] ?? '';
        $this->emailSubject = $vars['email_subject'] ?? '';
        $this->emailBody = $vars['email_body'] ?? '';
    }

    private function resetChannelTemplateKey(): void
    {
        $company = Auth::user()->currentCompany();
        $options = app(CampaignTemplateResolver::class)->optionsForChannel($company, $this->channel);

        $this->channelTemplateKey = $options->keys()->first();
        $this->loadChannelTemplateDefaults();
    }

    private function loadChannelTemplateDefaults(): void
    {
        if (! $this->channelTemplateKey) {
            return;
        }

        $resolved = app(CampaignTemplateResolver::class)->resolve(
            Auth::user()->currentCompany(),
            $this->channel,
            $this->channelTemplateKey
        );

        if (! $resolved) {
            return;
        }

        if ($this->channel === Campaign::CHANNEL_SMS && $this->channelTemplateKey !== 'custom') {
            $this->smsBody = $resolved['body'] ?? '';
        }

        if ($this->channel === Campaign::CHANNEL_EMAIL) {
            $this->emailSubject = $resolved['subject'] ?? '';
            $this->emailBody = $resolved['body'] ?? '';
        }
    }

    private function refreshEstimate(): void
    {
        $company = Auth::user()->currentCompany();
        $options = [
            'group_id' => $this->groupId,
            'segment_id' => $this->segmentId,
            'contact_id' => $this->contactId,
        ];

        if ($this->broadcastType === 'quick' && $this->quickPhones !== '') {
            $options['quick_phone_count'] = count(array_filter(preg_split('/[\n,]+/', $this->quickPhones)));
        }

        if ($this->broadcastType === 'file' && $this->fileRowCount > 0) {
            $options['file_row_count'] = $this->fileRowCount;
        }

        $template = $this->templateId ? Template::find($this->templateId) : null;

        $this->estimate = app(CampaignEstimateService::class)->estimateForChannel(
            $company,
            $this->channel,
            $options,
            $template
        );
    }

    /**
     * @param  array<int, string>  $headers
     */
    private function guessRecipientColumn(array $headers): string
    {
        foreach ($headers as $header) {
            $lower = strtolower($header);
            if ($this->channel === Campaign::CHANNEL_EMAIL && str_contains($lower, 'email')) {
                return $header;
            }
            if (str_contains($lower, 'phone') || str_contains($lower, 'mobile') || str_contains($lower, 'tel')) {
                return $header;
            }
        }

        return $headers[0] ?? '';
    }

    public function previewHeaderText(string $text): string
    {
        return $this->replacePreviewPlaceholders($text, 'header');
    }

    public function previewBodyText(string $text): string
    {
        return $this->replacePreviewPlaceholders($text, 'body');
    }

    private function replacePreviewPlaceholders(string $text, string $section): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', function (array $matches) use ($section): string {
            $id = $matches[1];
            $manual = $this->paramvalues[$section][$id] ?? '';

            if ($manual !== '') {
                return $manual;
            }

            $match = (int) ($this->parammatch[$section][$id] ?? -2);

            return match ($match) {
                -1 => 'John',
                0 => '+1234567890',
                default => '{{'.$id.'}}',
            };
        }, $text) ?? $text;
    }

    public function render(): View
    {
        $company = Auth::user()->currentCompany();
        $resolver = app(CampaignTemplateResolver::class);
        $parser = app(CampaignTemplateVariablesParser::class);

        $templateOptions = $resolver->optionsForChannel($company, $this->channel);

        $groups = Group::pluck('name', 'id');
        $groups = collect([0 => __('Send to all contacts')])->union($groups);

        $contactFields = [-2 => __('Use manually defined value'), -1 => __('Contact name'), 0 => __('Contact phone')];
        foreach (Field::pluck('name', 'id') as $key => $value) {
            $contactFields[$key] = $value;
        }

        $selectedTemplate = ($this->channel === Campaign::CHANNEL_WHATSAPP && $this->templateId)
            ? Template::find($this->templateId)
            : null;

        $variables = $selectedTemplate ? $parser->parse($selectedTemplate) : null;
        $templateComponents = $selectedTemplate ? $parser->components($selectedTemplate) : null;

        if ($this->step >= 4) {
            $this->refreshEstimate();
        }

        return view('livewire.campaign-wizard', [
            'templateOptions' => $templateOptions,
            'groups' => $groups,
            'segments' => CampaignSegment::pluck('name', 'id'),
            'contactFields' => $contactFields,
            'variables' => $variables,
            'templateComponents' => $templateComponents,
            'selectedTemplate' => $selectedTemplate,
            'channels' => [
                Campaign::CHANNEL_WHATSAPP => 'WhatsApp',
                Campaign::CHANNEL_SMS => 'SMS',
                Campaign::CHANNEL_EMAIL => 'Email',
            ],
            'broadcastTypes' => [
                'group' => __('Group broadcast'),
                'file' => __('File broadcast'),
                'quick' => __('Quick broadcast'),
            ],
        ]);
    }
}
