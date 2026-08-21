<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Scopes\CompanyScope;
use App\Services\Security\SafeRemoteUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [1, 5, 25, 120, 600, 3600, 21600, 86400];
    }

    public function __construct(public int $deliveryId)
    {
    }

    public function handle(SafeRemoteUrl $safeRemoteUrl): void
    {
        $delivery = WebhookDelivery::withoutGlobalScope(CompanyScope::class)->find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $endpoint = WebhookEndpoint::withoutGlobalScope(CompanyScope::class)->find($delivery->webhook_endpoint_id);

        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED, 'last_error' => 'Endpoint missing or inactive']);

            return;
        }

        if (! $safeRemoteUrl->isPublicHttpUrl($endpoint->url)) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED, 'last_error' => 'Webhook URL is not a public HTTP URL']);

            return;
        }

        $body = json_encode($delivery->payload);
        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $endpoint->secret);

        $delivery->increment('attempts');

        try {
            $response = Http::timeout((int) config('public-api.webhook.timeout', 10))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    config('public-api.webhook.signature_header') => 't='.$timestamp.',v1='.$signature,
                    config('public-api.webhook.timestamp_header') => $timestamp,
                    'X-ConvoConnect-Event' => $delivery->event_type,
                    'X-ConvoConnect-Delivery' => (string) $delivery->id,
                    'Idempotency-Key' => $delivery->event_id,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->http_status = $response->status();

            if ($response->successful()) {
                $delivery->status = WebhookDelivery::STATUS_DELIVERED;
                $delivery->last_error = null;
                $delivery->save();

                return;
            }

            $delivery->last_error = 'HTTP '.$response->status();
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->save();

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
            }
        } catch (\Throwable $e) {
            Log::warning('Webhook delivery exception', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            $delivery->last_error = $e->getMessage();
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->save();

            throw $e;
        }
    }
}
