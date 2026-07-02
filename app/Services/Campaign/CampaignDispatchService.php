<?php

namespace App\Services\Campaign;

use App\Models\Company;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use App\Services\Campaign\Channels\CampaignChannelRegistry;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignDispatchService
{
    public function __construct(
        private readonly CreditCharger $charger,
        private readonly CreditBillingResolver $billingResolver,
        private readonly CampaignChannelRegistry $channels,
        private readonly CampaignWebhookDispatcher $webhooks,
    ) {
    }

    public function dispatchPendingBatch(?int $limit = null): int
    {
        $limit = $limit ?? (int) config('wpbox.campaign_sending_batch', 100);

        if (! is_numeric($limit) || $limit < 1) {
            $limit = 100;
        }

        $messages = Message::query()
            ->where('status', Message::STATUS_PENDING)
            ->where('scchuduled_at', '<', now())
            ->whereIn('campaign_id', function ($query) {
                $query->select('id')
                    ->from('wa_campaings')
                    ->where('is_active', true)
                    ->whereNotIn('status', [Campaign::STATUS_DRAFT, Campaign::STATUS_CANCELLED, Campaign::STATUS_PAUSED_INSUFFICIENT_CREDITS]);
            })
            ->limit($limit)
            ->get();

        $sent = 0;

        foreach ($messages as $message) {
            if ($this->send($message)) {
                $sent++;
            }
        }

        cache()->put('campaign_dispatcher_last_run', now()->toIso8601String(), now()->addDay());

        return $sent;
    }

    public function send(Message $message, bool $useQueue = false): bool
    {
        if ($useQueue && config('wpbox.campaign_sending_type', 'normal') !== 'normal') {
            \Modules\Wpbox\Jobs\SendMessage::dispatch($message);

            return true;
        }

        return $this->sendSynchronously($message);
    }

    public function sendSynchronously(Message $message): bool
    {
        $message->loadMissing(['campaign.company', 'campaign.template', 'contact']);

        $company = null;

        try {
            $company = $message->campaign->company;
            $message->contact->phone;
        } catch (\Throwable $th) {
            Log::error('Campaign dispatch: missing company or contact', ['message_id' => $message->id, 'error' => $th->getMessage()]);
            $message->error = 'The company or contact is not found';
            $message->status = Message::STATUS_SENT;
            $message->save();

            return false;
        }

        if (! $company) {
            return false;
        }

        $campaign = $message->campaign;
        $channel = $campaign->channel ?? Campaign::CHANNEL_WHATSAPP;

        if ($channel === Campaign::CHANNEL_WHATSAPP) {
            return $this->sendWhatsApp($message, $company);
        }

        return $this->channels->send($channel, $message, $company);
    }

    private function sendWhatsApp(Message $message, Company $company): bool
    {
        $template = $message->campaign?->template;
        $creditAction = $this->billingResolver->resolveCampaignTemplateAction($template);

        if (! $this->charger->canCharge($company, $creditAction)) {
            $message->error = $this->charger->insufficientCreditsMessage($creditAction);
            $message->status = Message::STATUS_FAILED;
            $message->save();

            $this->pauseCampaignForInsufficientCredits($message->campaign);
            $this->webhooks->dispatchMessageFailed($message);

            return false;
        }

        $phoneId = $company->getConfig('whatsapp_phone_number_id', '');
        $accessToken = $company->getConfig('whatsapp_permanent_access_token', '');
        $url = 'https://graph.facebook.com/v19.0/'.$phoneId.'/messages';

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $message->contact->phone,
                'type' => 'template',
                'template' => [
                    'name' => $message->campaign->template->name,
                    'language' => [
                        'code' => $message->campaign->template->language,
                    ],
                    'components' => json_decode($message->components),
                ],
            ]);

            $content = json_decode($response->body(), true);
            $message->created_at = now();

            if (isset($content['messages'])) {
                $message->fb_message_id = $content['messages'][0]['id'];
                $this->charger->charge($company, $creditAction, $company->id);
            } else {
                $message->error = $content['error']['message'] ?? 'Unknown error';
                $message->status = Message::STATUS_FAILED;
                $message->save();
                $this->webhooks->dispatchMessageFailed($message);

                return false;
            }

            $message->status = Message::STATUS_SENT;
            $message->save();

            if ($message->campaign) {
                $message->campaign->increment('sended_to');
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Campaign WhatsApp send failed', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            $message->error = $e->getMessage();
            $message->status = Message::STATUS_FAILED;
            $message->save();
            $this->webhooks->dispatchMessageFailed($message);

            return false;
        }
    }

    private function pauseCampaignForInsufficientCredits(Campaign $campaign): void
    {
        $pending = $campaign->messages()->where('status', Message::STATUS_PENDING)->count();

        if ($pending > 0) {
            $campaign->update([
                'status' => Campaign::STATUS_PAUSED_INSUFFICIENT_CREDITS,
                'is_active' => false,
            ]);
        }
    }
}
