<?php

namespace App\Services\Campaign;

use App\Jobs\Campaign\SendCampaignMessageJob;
use App\Jobs\Campaign\SendCampaignSmsBatchJob;
use App\Models\Company;
use App\Services\Billing\CreditBillingResolver;
use App\Services\Billing\CreditCharger;
use App\Services\Campaign\Channels\CampaignChannelRegistry;
use App\Services\Campaign\Channels\SmsCampaignBatchSender;
use Illuminate\Support\Facades\Log;
use Modules\Wpbox\Models\Campaign;
use Modules\Wpbox\Models\Message;

class CampaignDispatchService
{
    public function __construct(
        private readonly CreditCharger $charger,
        private readonly CreditBillingResolver $billingResolver,
        private readonly CampaignChannelRegistry $channels,
        private readonly SmsCampaignBatchSender $smsBatchSender,
        private readonly CampaignWebhookDispatcher $webhooks,
        private readonly WhatsAppRateLimiter $rateLimiter,
        private readonly CampaignCounterService $counters,
        private readonly CampaignMetricsService $metrics,
    ) {
    }

    public function enqueuePendingBatch(?int $limit = null): int
    {
        if (! $this->shouldUseAsyncDispatch()) {
            $sent = $this->dispatchPendingBatch($limit);
            $this->metrics->recordDispatchRun($sent, 0);

            return $sent;
        }

        $messages = $this->pendingMessagesQuery($limit)->get();
        $queued = 0;
        $smsMessages = collect();
        $otherMessages = collect();

        foreach ($messages as $message) {
            if (($message->campaign->channel ?? Campaign::CHANNEL_WHATSAPP) === Campaign::CHANNEL_SMS) {
                $smsMessages->push($message);
            } else {
                $otherMessages->push($message);
            }
        }

        if ($smsMessages->isNotEmpty()) {
            SendCampaignSmsBatchJob::dispatch($smsMessages->pluck('id')->all());
            $queued += $smsMessages->count();
        }

        foreach ($otherMessages as $message) {
            SendCampaignMessageJob::dispatch($message->id);
            $queued++;
        }

        cache()->put('campaign_dispatcher_last_run', now()->toIso8601String(), now()->addDay());
        $this->metrics->recordDispatchRun(0, $queued);

        return $queued;
    }

    public function dispatchPendingBatch(?int $limit = null): int
    {
        $messages = $this->pendingMessagesQuery($limit)->get();

        $sent = 0;
        $smsMessages = collect();
        $otherMessages = collect();

        foreach ($messages as $message) {
            if (($message->campaign->channel ?? Campaign::CHANNEL_WHATSAPP) === Campaign::CHANNEL_SMS) {
                $smsMessages->push($message);
            } else {
                $otherMessages->push($message);
            }
        }

        if ($smsMessages->isNotEmpty()) {
            $sent += $this->smsBatchSender->dispatch($smsMessages);
        }

        foreach ($otherMessages as $message) {
            if ($this->sendSynchronously($message)) {
                $sent++;
            }
        }

        cache()->put('campaign_dispatcher_last_run', now()->toIso8601String(), now()->addDay());

        return $sent;
    }

    public function send(Message $message, bool $useQueue = false): bool
    {
        if ($useQueue && $this->shouldUseAsyncDispatch()) {
            SendCampaignMessageJob::dispatch($message->id);

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

    /**
     * @param  array<int, int>  $messageIds
     */
    public function dispatchSmsBatch(array $messageIds): int
    {
        if ($messageIds === []) {
            return 0;
        }

        $messages = Message::withoutGlobalScopes()
            ->with(['campaign.company', 'contact'])
            ->whereIn('id', $messageIds)
            ->where('status', Message::STATUS_PENDING)
            ->get();

        return $this->smsBatchSender->dispatch($messages);
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

        $phoneId = (string) $company->getConfig('whatsapp_phone_number_id', '');

        if ($phoneId !== '' && ! $this->rateLimiter->acquire($phoneId)) {
            $message->error = 'WhatsApp rate limit reached';
            $message->save();

            return false;
        }

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
                $this->counters->bufferSent((int) $message->campaign->id);
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

    private function shouldUseAsyncDispatch(): bool
    {
        if (! config('wpbox.campaign_async_dispatch', true)) {
            return false;
        }

        return config('queue.default') !== 'sync';
    }

    private function pendingMessagesQuery(?int $limit = null)
    {
        $limit = $limit ?? (int) config('wpbox.campaign_sending_batch', 500);

        if (! is_numeric($limit) || $limit < 1) {
            $limit = 500;
        }

        return Message::query()
            ->with(['campaign', 'contact'])
            ->where('status', Message::STATUS_PENDING)
            ->where('scchuduled_at', '<', now())
            ->whereIn('campaign_id', function ($query) {
                $query->select('id')
                    ->from('wa_campaings')
                    ->where('is_active', true)
                    ->whereNotIn('status', [
                        Campaign::STATUS_DRAFT,
                        Campaign::STATUS_CANCELLED,
                        Campaign::STATUS_PAUSED_INSUFFICIENT_CREDITS,
                        Campaign::STATUS_PREPARING,
                        Campaign::STATUS_PREPARATION_FAILED,
                    ]);
            })
            ->limit($limit);
    }
}
