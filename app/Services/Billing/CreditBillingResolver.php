<?php

namespace App\Services\Billing;

use Carbon\Carbon;
use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Template;

class CreditBillingResolver
{
    public function resolveInboxOutboundAction(Contact $contact, bool $isBotAutoReply = false, ?string $explicitAction = null): string
    {
        if ($explicitAction !== null) {
            return $explicitAction;
        }

        if ($isBotAutoReply) {
            return 'send_bot_auto_reply';
        }

        if ($this->isWithinServiceWindow($contact)) {
            return 'send_service_window_reply';
        }

        return 'send_outside_window_reply';
    }

    public function resolveCampaignTemplateAction(?Template $template): string
    {
        if ($template === null) {
            return 'send_campaign_marketing';
        }

        return match (strtoupper((string) $template->category)) {
            'UTILITY', 'AUTHENTICATION' => 'send_template_utility',
            default => 'send_campaign_marketing',
        };
    }

    public function isWithinServiceWindow(Contact $contact): bool
    {
        if ($contact->last_client_reply_at === null) {
            return false;
        }

        $hours = (int) config('credit-actions.service_window_hours', 24);

        return Carbon::parse($contact->last_client_reply_at)->greaterThan(now()->subHours($hours));
    }
}
