<?php

namespace App\Services\Campaign;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignWebhookDispatcher
{
    public function dispatchMessageFailed(Message $message): void
    {
        $message->loadMissing(['campaign.company', 'contact']);

        $this->send($message->company_id ?? $message->campaign?->company_id, 'message.failed', [
            'campaign_id' => $message->campaign_id,
            'message_id' => $message->id,
            'contact_id' => $message->contact_id,
            'phone' => $message->contact?->phone,
            'error' => $message->error,
            'status' => $message->status,
        ]);
    }

    public function dispatchCampaignCompleted(Campaign $campaign): void
    {
        $campaign->loadMissing('company');

        $this->send($campaign->company_id, 'campaign.completed', [
            'campaign_id' => $campaign->id,
            'name' => $campaign->name,
            'send_to' => $campaign->send_to,
            'sended_to' => $campaign->sended_to,
            'delivered_to' => $campaign->delivered_to,
            'read_by' => $campaign->read_by,
            'status' => $campaign->status,
            'completed_at' => $campaign->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(?int $companyId, string $event, array $payload): void
    {
        if (! $companyId) {
            return;
        }

        $company = \App\Models\Company::find($companyId);

        if (! $company) {
            return;
        }

        $url = $company->getConfig('campaign_webhook_url', '');

        if (empty($url)) {
            $url = $company->getConfig('whatsapp_data_send_webhook', '');
        }

        if (empty($url)) {
            return;
        }

        try {
            Http::timeout(10)->post($url, [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Campaign webhook dispatch failed', [
                'event' => $event,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);
        }

        app(\App\Services\Api\PublicWebhookDispatcher::class)->dispatch($companyId, $event, $payload);
    }
}
