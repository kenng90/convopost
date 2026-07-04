<?php

namespace App\Services\Campaign;

use App\Services\Campaign\Templates\CampaignTemplateResolver;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignShowPresenter
{
    public function __construct(
        private readonly Campaign $campaign,
        private readonly CampaignTemplateResolver $templateResolver,
        private readonly CampaignTemplateVariablesParser $variablesParser,
        private readonly CampaignAnalyticsService $analytics,
    ) {
    }

    public static function for(Campaign $campaign): self
    {
        $campaign->loadMissing(['template', 'segment', 'company']);

        return app(self::class, ['campaign' => $campaign]);
    }

    public function campaign(): Campaign
    {
        return $this->campaign;
    }

    public function channel(): string
    {
        return $this->campaign->channel ?? Campaign::CHANNEL_WHATSAPP;
    }

    public function channelLabel(): string
    {
        return match ($this->channel()) {
            Campaign::CHANNEL_SMS => 'SMS',
            Campaign::CHANNEL_EMAIL => 'Email',
            default => 'WhatsApp',
        };
    }

    public function broadcastTypeLabel(): string
    {
        if ($this->campaign->is_api) {
            return __('API campaign');
        }

        if ($this->campaign->is_bot) {
            return __('Bot');
        }

        if ($this->campaign->is_reminder) {
            return __('Reminder');
        }

        return match ($this->campaign->broadcast_type) {
            'file' => __('File broadcast'),
            'quick' => __('Quick broadcast'),
            default => __('Group broadcast'),
        };
    }

    public function statusLabel(): string
    {
        if ($this->campaign->is_api) {
            return $this->campaign->is_active && $this->campaign->status !== Campaign::STATUS_INACTIVE
                ? __('Active')
                : __('Inactive');
        }

        $status = $this->campaign->status ?? '';

        return ucfirst(str_replace('_', ' ', $status));
    }

    public function isApiCampaign(): bool
    {
        return (bool) $this->campaign->is_api;
    }

    /**
     * @return array<int, array{section: string, id: string, path: string}>
     */
    public function apiVariablePaths(): array
    {
        return app(ApiCampaignService::class)->apiVariablePaths($this->campaign);
    }

    /**
     * @return array<string, mixed>
     */
    public function samplePayload(?string $token = null): array
    {
        return app(ApiCampaignService::class)->samplePayload($this->campaign, $token);
    }

    public function sampleCurl(?string $token = null): string
    {
        return app(ApiCampaignService::class)->sampleCurl($this->campaign, $token);
    }

    public function sendEndpoint(): string
    {
        return rtrim(config('app.url'), '/').'/api/wpbox/sendcampaigns';
    }

    public function templateLabel(): string
    {
        $company = $this->campaign->company;

        if (! $company) {
            return __('Unknown');
        }

        if ($this->channel() === Campaign::CHANNEL_WHATSAPP) {
            return $this->campaign->template?->name ?? __('Unknown template');
        }

        $key = $this->campaign->channel_template_key;

        if ($this->channel() === Campaign::CHANNEL_EMAIL && empty($key)) {
            return __('Custom content');
        }

        $resolved = $this->templateResolver->resolve(
            $company,
            $this->channel(),
            $key ?? 'custom'
        );

        return $resolved['name'] ?? ($this->channel() === Campaign::CHANNEL_SMS
            ? __('Custom message')
            : __('Custom content'));
    }

    public function scheduleSummary(): ?string
    {
        $parts = [];

        if ($this->campaign->timestamp_for_delivery) {
            $parts[] = __('Scheduled for').': '.$this->campaign->timestamp_for_delivery;
        } elseif ($this->campaign->launched_at) {
            $parts[] = __('Launched').': '.$this->campaign->launched_at->format('Y-m-d H:i');
        }

        if ($this->campaign->timezone_mode === Campaign::TIMEZONE_MODE_CONTACT) {
            $parts[] = __('Per contact local time');
        } elseif ($this->campaign->timezone_mode === Campaign::TIMEZONE_MODE_BUSINESS) {
            $parts[] = __('Business timezone');
        }

        if (is_array($this->campaign->recurrence_rule) && ! empty($this->campaign->recurrence_rule['interval'])) {
            $parts[] = __('Recurring').': '.$this->campaign->recurrence_rule['interval'];
        }

        if ($this->campaign->segment) {
            $parts[] = __('Segment').': '.$this->campaign->segment->name;
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    public function showMap(): bool
    {
        return $this->channel() !== Campaign::CHANNEL_EMAIL
            && ! $this->campaign->is_bot
            && ! $this->campaign->is_api;
    }

    public function usesWhatsAppDeliveryMetrics(): bool
    {
        return $this->channel() === Campaign::CHANNEL_WHATSAPP;
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(): array
    {
        return $this->analytics->summarizeForChannel($this->campaign);
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function messageTableHeaders(): array
    {
        if ($this->channel() === Campaign::CHANNEL_EMAIL) {
            return [
                ['key' => 'email', 'label' => __('Email')],
                ['key' => 'name', 'label' => __('Name')],
                ['key' => 'subject', 'label' => __('Subject')],
                ['key' => 'message', 'label' => __('Message')],
                ['key' => 'status', 'label' => __('Status')],
            ];
        }

        return [
            ['key' => 'phone', 'label' => __('Phone')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'message', 'label' => __('Message')],
            ['key' => 'status', 'label' => __('Status')],
        ];
    }

    public function messageStatusLabel(Message $message): string
    {
        $errorSuffix = $message->error ? ' — '.$message->error : '';

        return match ((int) $message->status) {
            Message::STATUS_PENDING => __('Pending').$errorSuffix,
            Message::STATUS_SENT, Message::STATUS_SENT_ALT => __('Sent').$errorSuffix,
            Message::STATUS_DELIVERED => $this->usesWhatsAppDeliveryMetrics()
                ? __('Delivered').$errorSuffix
                : __('Sent').$errorSuffix,
            Message::STATUS_READ => $this->usesWhatsAppDeliveryMetrics()
                ? __('Read').$errorSuffix
                : __('Sent').$errorSuffix,
            Message::STATUS_FAILED => __('Failed').($message->error ? ': '.$message->error : ''),
            Message::STATUS_CANCELLED => __('Cancelled'),
            default => __('Unknown'),
        };
    }

    public function messageStatusClass(Message $message): string
    {
        return match ((int) $message->status) {
            Message::STATUS_PENDING => 'warning',
            Message::STATUS_SENT, Message::STATUS_SENT_ALT => 'info',
            Message::STATUS_DELIVERED => 'info',
            Message::STATUS_READ => 'success',
            Message::STATUS_FAILED => 'danger',
            Message::STATUS_CANCELLED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function messageRow(Message $message): array
    {
        $contact = $message->contact;

        return [
            'phone' => $contact->phone ?? __('Unknown'),
            'email' => $contact->email ?? __('Unknown'),
            'name' => $contact->name ?? __('Unknown'),
            'subject' => $message->header_text ?: '—',
            'message' => $message->value,
            'status' => $this->messageStatusLabel($message),
            'status_class' => $this->messageStatusClass($message),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contentPreview(): array
    {
        $variables = json_decode($this->campaign->variables, true) ?? [];

        if ($this->channel() === Campaign::CHANNEL_SMS) {
            return [
                'channel' => Campaign::CHANNEL_SMS,
                'body' => $variables['sms_body'] ?? '',
            ];
        }

        if ($this->channel() === Campaign::CHANNEL_EMAIL) {
            return [
                'channel' => Campaign::CHANNEL_EMAIL,
                'subject' => $variables['email_subject'] ?? $this->campaign->name,
                'body' => $variables['email_body'] ?? '',
            ];
        }

        $template = $this->campaign->template;

        if (! $template) {
            return [
                'channel' => Campaign::CHANNEL_WHATSAPP,
                'components' => [],
            ];
        }

        $components = $this->variablesParser->components($template) ?? [];

        return [
            'channel' => Campaign::CHANNEL_WHATSAPP,
            'components' => $this->applyWhatsAppPreviewVariables($components, $variables),
            'template_name' => $template->name,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function reportHeaders(): array
    {
        if ($this->channel() === Campaign::CHANNEL_EMAIL) {
            return ['Name', 'Email', 'Subject', 'Message', 'Status', 'Sent at', 'Last status update', 'Error'];
        }

        return ['Name', 'Phone', 'Country', 'Message', 'Status', 'Sent at', 'Last status update', 'Error'];
    }

    /**
     * @return array<int, string|null>
     */
    public function reportRow(Message $message): array
    {
        $contact = $message->contact;
        $status = $this->reportStatusCode($message);
        $sentAt = $message->scchuduled_at ?: $message->created_at;
        $country = '';

        try {
            $country = $contact?->country?->name ?? '';
        } catch (\Throwable $th) {
        }

        if ($this->channel() === Campaign::CHANNEL_EMAIL) {
            return [
                $contact->name ?? '',
                $contact->email ?? '',
                $message->header_text ?? '',
                $message->value ?? '',
                $status,
                $sentAt ? (string) $sentAt : '',
                $message->updated_at ? (string) $message->updated_at : '',
                $message->error ?? '',
            ];
        }

        return [
            $contact->name ?? '',
            $contact->phone ?? '',
            $country,
            $message->value ?? '',
            $status,
            $sentAt ? (string) $sentAt : '',
            $message->updated_at ? (string) $message->updated_at : '',
            $message->error ?? '',
        ];
    }

    private function reportStatusCode(Message $message): string
    {
        return match ((int) $message->status) {
            Message::STATUS_PENDING => 'PENDING',
            Message::STATUS_SENT, Message::STATUS_SENT_ALT => 'SENT',
            Message::STATUS_DELIVERED => $this->usesWhatsAppDeliveryMetrics() ? 'DELIVERED' : 'SENT',
            Message::STATUS_READ => $this->usesWhatsAppDeliveryMetrics() ? 'READ' : 'SENT',
            Message::STATUS_FAILED => 'FAILED',
            Message::STATUS_CANCELLED => 'CANCELLED',
            default => 'UNKNOWN',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<string, mixed>  $variables
     * @return array<int, array<string, mixed>>
     */
    private function applyWhatsAppPreviewVariables(array $components, array $variables): array
    {
        foreach ($components as $index => $component) {
            if (($component['type'] ?? '') === 'HEADER' && ($component['format'] ?? '') === 'TEXT') {
                $components[$index]['text'] = $this->replaceWhatsAppVariables(
                    $component['text'] ?? '',
                    'header',
                    $variables
                );
            }

            if (($component['type'] ?? '') === 'BODY') {
                $components[$index]['text'] = $this->replaceWhatsAppVariables(
                    $component['text'] ?? '',
                    'body',
                    $variables
                );
            }
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    private function replaceWhatsAppVariables(string $text, string $section, array $variables): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', function (array $matches) use ($section, $variables): string {
            $id = $matches[1];

            return (string) ($variables[$section][$id] ?? '{{'.$id.'}}');
        }, $text) ?? $text;
    }
}
