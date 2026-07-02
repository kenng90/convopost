<?php

namespace App\Services\Campaign\Templates;

use App\Models\Company;
use Illuminate\Support\Collection;
use Modules\Wpbox\Models\Campaign;

class CampaignTemplateResolver
{
    public function __construct(
        private readonly WhatsAppTemplateProvider $whatsApp,
        private readonly SmsTemplateProvider $sms,
        private readonly EmailTemplateProvider $email,
    ) {
    }

    /**
     * @return Collection<int|string, string>
     */
    public function optionsForChannel(Company $company, string $channel): Collection
    {
        return match ($channel) {
            Campaign::CHANNEL_SMS => $this->sms->options($company),
            Campaign::CHANNEL_EMAIL => $this->email->options($company),
            default => $this->whatsApp->options($company),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(Company $company, string $channel, int|string|null $templateKey): ?array
    {
        return match ($channel) {
            Campaign::CHANNEL_SMS => $this->sms->resolve($company, (string) $templateKey),
            Campaign::CHANNEL_EMAIL => $this->email->resolve($company, (string) $templateKey),
            default => $this->whatsApp->resolve($company, (int) $templateKey),
        };
    }

    public function requiresWhatsAppTemplate(string $channel): bool
    {
        return $channel === Campaign::CHANNEL_WHATSAPP;
    }
}
