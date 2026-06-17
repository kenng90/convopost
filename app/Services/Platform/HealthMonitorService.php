<?php

namespace App\Services\Platform;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Template;

class HealthMonitorService
{
    /**
     * @return array<int, array{severity: string, code: string, title: string, message: string, action_route: ?string}>
     */
    public function alerts(Company $company): array
    {
        $alerts = [];

        if ($company->getConfig('whatsapp_webhook_verified', 'no') !== 'yes') {
            $alerts[] = $this->alert('critical', 'webhook_unverified', __('WhatsApp webhook not verified'), __('Inbound messages will not arrive until Meta verifies your webhook.'), 'whatsapp.setup');
        }

        if ($company->getConfig('whatsapp_settings_done', 'no') !== 'yes') {
            $alerts[] = $this->alert('critical', 'whatsapp_incomplete', __('WhatsApp credentials incomplete'), __('Add your permanent token, phone number ID, and business account ID.'), 'whatsapp.setup');
        }

        if (! $this->isPusherConfigured()) {
            $alerts[] = $this->alert('warning', 'pusher_missing', __('Real-time chat may not update'), __('Configure Pusher keys in site settings for live inbox updates.'), null);
        }

        $rejectedTemplates = Template::where('status', 'REJECTED')->count();
        if ($rejectedTemplates > 0) {
            $alerts[] = $this->alert('warning', 'templates_rejected', __(':count template(s) rejected', ['count' => $rejectedTemplates]), __('Review and resubmit rejected WhatsApp templates.'), 'templates.index');
        }

        $blockedCampaigns = Campaign::where('is_active', false)->where('send_to', '>', 0)->count();
        if ($blockedCampaigns > 0) {
            $alerts[] = $this->alert('info', 'campaigns_paused', __(':count campaign(s) paused', ['count' => $blockedCampaigns]), __('Some campaigns are inactive or paused.'), 'campaigns.index');
        }

        if ($this->tokenMayBeExpired($company)) {
            $alerts[] = $this->alert('warning', 'token_check', __('Verify WhatsApp access token'), __('We could not validate your token with Meta. Reconnect if sends are failing.'), 'whatsapp.setup');
        }

        return $alerts;
    }

    public function healthyCount(Company $company): int
    {
        return count($this->alerts($company));
    }

    private function alert(string $severity, string $code, string $title, string $message, ?string $route): array
    {
        return [
            'severity' => $severity,
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'action_route' => $route,
        ];
    }

    private function isPusherConfigured(): bool
    {
        return filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'))
            && filled(config('broadcasting.connections.pusher.app_id'));
    }

    private function tokenMayBeExpired(Company $company): bool
    {
        $token = $company->getConfig('whatsapp_permanent_access_token', '');
        $phoneId = $company->getConfig('whatsapp_phone_number_id', '');

        if (strlen($token) < 20 || strlen($phoneId) < 4) {
            return false;
        }

        try {
            $response = Http::timeout(5)->withToken($token)
                ->get("https://graph.facebook.com/v19.0/{$phoneId}");

            return $response->failed();
        } catch (\Throwable) {
            return false;
        }
    }
}
