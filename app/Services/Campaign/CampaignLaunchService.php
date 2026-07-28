<?php

namespace App\Services\Campaign;

use App\Jobs\Campaign\PrepareCampaignMessagesJob;
use App\Models\Company;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use App\Services\Billing\CreditCostService;
use App\Services\Campaign\Templates\CampaignTemplateResolver;
use App\Services\Campaign\Templates\EmailTemplateProvider;
use App\Services\Campaign\Templates\SmsTemplateProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;

class CampaignLaunchService
{
    public function __construct(
        private readonly CampaignAudienceResolver $audience,
        private readonly CampaignFileParser $fileParser,
        private readonly CampaignTemplateResolver $templateResolver,
        private readonly CreditBillingResolver $billingResolver,
        private readonly CreditCharger $charger,
        private readonly CreditCostService $costs,
        private readonly CampaignMediaService $mediaService,
        private readonly CampaignRecipientGuard $recipientGuard,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function launch(Company $company, array $payload, bool $draft = false): Campaign
    {
        $channel = $payload['channel'] ?? Campaign::CHANNEL_WHATSAPP;
        $broadcastType = $payload['broadcast_type'] ?? 'group';

        $this->validateChannelTemplate($company, $channel, $payload);

        if (! $draft) {
            $this->assertSufficientCredits($company, $channel, $payload);
            $this->recipientGuard->assertWithinLimit($this->resolveRecipientCount($company, $payload));
        }

        return match ($broadcastType) {
            'file' => $this->launchFileBroadcast($company, $payload, $draft),
            'quick' => $this->launchQuickBroadcast($company, $payload, $draft),
            default => $this->launchGroupBroadcast($company, $payload, $draft),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function launchGroupBroadcast(Company $company, array $payload, bool $draft): Campaign
    {
        $attributes = $this->baseCampaignAttributes($company, $payload, $draft, 'group');

        if ($payload['draft_id'] ?? null) {
            $campaign = Campaign::findOrFail($payload['draft_id']);
            $campaign->update($attributes);
        } else {
            $campaign = Campaign::create($attributes);
        }

        $this->mediaService->attachFromPayload($campaign, $payload);

        if ($draft) {
            return $campaign;
        }

        $this->queuePreparation($campaign, $payload);

        return $campaign->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function launchQuickBroadcast(Company $company, array $payload, bool $draft): Campaign
    {
        $phones = $this->parseQuickPhones($payload['quick_phones'] ?? '');

        if ($phones->isEmpty()) {
            throw ValidationException::withMessages([
                'quick_phones' => [__('No valid phone numbers found.')],
            ]);
        }

        if (($payload['channel'] ?? Campaign::CHANNEL_WHATSAPP) === Campaign::CHANNEL_EMAIL) {
            throw ValidationException::withMessages([
                'quick_phones' => [__('Quick broadcast is not available for email campaigns.')],
            ]);
        }

        $attributes = $this->baseCampaignAttributes($company, $payload, $draft, 'quick');
        $campaign = Campaign::create($attributes);
        $this->mediaService->attachFromPayload($campaign, $payload);

        if ($draft) {
            return $campaign;
        }

        $this->queuePreparation($campaign, $payload);

        return $campaign->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function launchFileBroadcast(Company $company, array $payload, bool $draft): Campaign
    {
        /** @var UploadedFile $file */
        $file = $payload['contact_file'];
        $channel = $payload['channel'] ?? Campaign::CHANNEL_WHATSAPP;
        $data = $this->fileParser->parseFromPath($file->getRealPath(), $file->getClientOriginalExtension());
        $headers = $data['headers'];
        $recipientColumn = $payload['recipient_column'] ?? $payload['phone_column'] ?? '';
        $columnIndex = $this->fileParser->resolveColumnIndex($headers, $recipientColumn);

        if ($columnIndex === false) {
            throw ValidationException::withMessages([
                'recipient_column' => [__('Selected recipient column not found in file.')],
            ]);
        }

        $validCount = $this->fileParser->countValidRecipientRows(
            $data['rows'],
            $headers,
            $columnIndex,
            $channel
        );

        if ($validCount === 0) {
            throw ValidationException::withMessages([
                'contact_file' => [__('No valid recipients found in file.')],
            ]);
        }

        $attributes = $this->baseCampaignAttributes($company, $payload, $draft, 'file');
        $attributes['send_to'] = 0;
        $attributes['total_contacts'] = 0;

        $campaign = Campaign::create($attributes);
        $this->mediaService->attachFromPayload($campaign, $payload);

        if ($draft) {
            return $campaign;
        }

        $storedPath = $file->store('campaign-uploads/'.$campaign->id, 'local');
        $payload['contact_file_path'] = $storedPath;
        $payload['contact_file_extension'] = $file->getClientOriginalExtension();
        unset($payload['contact_file']);

        $payload['parammatch'] = $this->buildFileBroadcastParamMatch($payload['parammatch'] ?? [], $payload['file_column_map'] ?? []);

        $this->queuePreparation($campaign, $payload);

        return $campaign->fresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function queuePreparation(Campaign $campaign, array $payload): void
    {
        $storedPayload = $this->normalizeLaunchPayload($payload);

        $campaign->update([
            'status' => Campaign::STATUS_PREPARING,
            'launch_payload' => $storedPayload,
            'messages_prepared_count' => 0,
            'preparation_error' => null,
        ]);

        PrepareCampaignMessagesJob::dispatch($campaign->id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeLaunchPayload(array $payload): array
    {
        unset($payload['contact_file'], $payload['pdf'], $payload['imageupload']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function baseCampaignAttributes(Company $company, array $payload, bool $draft, string $broadcastType): array
    {
        $channel = $payload['channel'] ?? Campaign::CHANNEL_WHATSAPP;
        $variables = $this->buildVariablesPayload($company, $channel, $payload);

        return [
            'name' => $payload['name'] ?? 'campaign_'.now(),
            'company_id' => $company->id,
            'template_id' => $channel === Campaign::CHANNEL_WHATSAPP ? ($payload['template_id'] ?? null) : null,
            'channel_template_key' => $channel !== Campaign::CHANNEL_WHATSAPP ? ($payload['channel_template_key'] ?? 'custom') : null,
            'group_id' => ($payload['group_id'] ?? null) === 0 ? null : ($payload['group_id'] ?? null),
            'segment_id' => $payload['segment_id'] ?? null,
            'contact_id' => $payload['contact_id'] ?? null,
            'variables' => json_encode($variables),
            'variables_match' => json_encode($payload['parammatch'] ?? []),
            'timestamp_for_delivery' => ($payload['send_now'] ?? true) ? null : ($payload['send_time'] ?? null),
            'broadcast_type' => $broadcastType,
            'channel' => $channel,
            'timezone_mode' => $payload['timezone_mode'] ?? Campaign::TIMEZONE_MODE_CONTACT,
            'status' => $draft ? Campaign::STATUS_DRAFT : Campaign::STATUS_PREPARING,
            'total_contacts' => $this->audience->subscribedCount($company, [
                'group_id' => $payload['group_id'] ?? null,
                'segment_id' => $payload['segment_id'] ?? null,
                'contact_id' => $payload['contact_id'] ?? null,
            ]),
            'ab_variant' => $payload['ab_variant'] ?? null,
            'recurrence_rule' => $payload['recurrence_rule'] ?? null,
            'recurrence_next_at' => $payload['recurrence_next_at'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildVariablesPayload(Company $company, string $channel, array $payload): array
    {
        $paramvalues = $payload['paramvalues'] ?? [];

        if ($channel === Campaign::CHANNEL_SMS) {
            $resolved = $this->templateResolver->resolve($company, $channel, $payload['channel_template_key'] ?? 'custom');
            $body = $payload['sms_body'] ?? ($resolved['body'] ?? '');

            return array_merge($paramvalues, ['sms_body' => $body]);
        }

        if ($channel === Campaign::CHANNEL_EMAIL) {
            $resolved = $this->templateResolver->resolve($company, $channel, $payload['channel_template_key'] ?? '1');
            $subject = $payload['email_subject'] ?? ($resolved['subject'] ?? $payload['name'] ?? '');
            $body = $payload['email_body'] ?? ($resolved['body'] ?? '');

            return array_merge($paramvalues, [
                'email_subject' => $subject,
                'email_body' => $body,
            ]);
        }

        return $paramvalues;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateChannelTemplate(Company $company, string $channel, array $payload): void
    {
        if ($channel === Campaign::CHANNEL_WHATSAPP) {
            if (empty($payload['template_id'])) {
                throw ValidationException::withMessages(['template_id' => [__('Select a WhatsApp template.')]]);
            }

            $template = Template::where('company_id', $company->id)->find($payload['template_id']);
            if (! $template) {
                throw ValidationException::withMessages(['template_id' => [__('Invalid WhatsApp template.')]]);
            }

            return;
        }

        if ($channel === Campaign::CHANNEL_SMS) {
            if (($payload['channel_template_key'] ?? 'custom') === 'custom' && empty(trim($payload['sms_body'] ?? ''))) {
                throw ValidationException::withMessages(['sms_body' => [__('Enter an SMS message.')]]);
            }

            return;
        }

        if ($channel === Campaign::CHANNEL_EMAIL) {
            $key = $payload['channel_template_key'] ?? null;
            if ($key && $this->templateResolver->resolve($company, $channel, $key)) {
                return;
            }

            if (empty(trim($payload['email_subject'] ?? '')) || empty(trim($payload['email_body'] ?? ''))) {
                throw ValidationException::withMessages(['email_body' => [__('Select an email template or enter subject and body.')]]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertSufficientCredits(Company $company, string $channel, array $payload): void
    {
        if (! config('settings.enable_credits', false)) {
            return;
        }

        $recipientCount = $this->resolveRecipientCount($company, $payload);
        $creditAction = $this->resolveCreditAction($company, $channel, $payload);

        if (! $this->charger->canCharge($company, $creditAction, max(1, $recipientCount))) {
            $cost = $this->costs->getActionCost($creditAction, max(1, $recipientCount));
            throw ValidationException::withMessages([
                'credits' => [__('Insufficient credits. Required: :credits', ['credits' => $cost])],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCreditAction(Company $company, string $channel, array $payload): string
    {
        if ($channel === Campaign::CHANNEL_SMS) {
            return app(SmsTemplateProvider::class)->creditAction();
        }

        if ($channel === Campaign::CHANNEL_EMAIL) {
            return app(EmailTemplateProvider::class)->creditAction();
        }

        $template = Template::find($payload['template_id'] ?? 0);

        return $this->billingResolver->resolveCampaignTemplateAction($template);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveRecipientCount(Company $company, array $payload): int
    {
        $broadcastType = $payload['broadcast_type'] ?? 'group';

        if ($broadcastType === 'quick') {
            return $this->parseQuickPhones($payload['quick_phones'] ?? '')->count();
        }

        if ($broadcastType === 'file' && isset($payload['contact_file'])) {
            $file = $payload['contact_file'];
            $data = $this->fileParser->parseFromPath($file->getRealPath(), $file->getClientOriginalExtension());
            $column = $payload['recipient_column'] ?? $payload['phone_column'] ?? '';
            $index = $this->fileParser->resolveColumnIndex($data['headers'], $column);

            if ($index === false) {
                return 0;
            }

            return $this->fileParser->countValidRecipientRows(
                $data['rows'],
                $data['headers'],
                $index,
                $payload['channel'] ?? Campaign::CHANNEL_WHATSAPP
            );
        }

        return $this->audience->subscribedCount($company, [
            'group_id' => $payload['group_id'] ?? null,
            'segment_id' => $payload['segment_id'] ?? null,
            'contact_id' => $payload['contact_id'] ?? null,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function parseQuickPhones(string $raw): \Illuminate\Support\Collection
    {
        return collect(preg_split('/[\n,]+/', $raw))
            ->map(fn ($p) => $this->fileParser->normalizePhoneFromCell($p))
            ->filter()
            ->unique()
            ->values();
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
}
