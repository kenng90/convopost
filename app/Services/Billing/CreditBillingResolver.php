<?php

namespace App\Services\Billing;

use Modules\Wpbox\Models\Contact;
use Modules\Wpbox\Models\Template;

class CreditBillingResolver
{
    public function resolveInboxOutboundAction(Contact $contact, bool $isBotAutoReply = false, ?string $explicitAction = null): string
    {
        if ($explicitAction !== null) {
            return $explicitAction;
        }

        // In-session bot/flow replies use the free Meta service window, matching
        // UserReply and other flow nodes that call sendMessage without isBotAutoReply.
        if ($this->isWithinServiceWindow($contact)) {
            return 'send_service_window_reply';
        }

        if ($isBotAutoReply) {
            return 'send_bot_auto_reply';
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
        return app(\App\Services\WhatsApp\WhatsAppSessionWindow::class)->isOpen($contact);
    }
}
