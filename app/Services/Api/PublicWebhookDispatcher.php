<?php

namespace App\Services\Api;

use App\Jobs\DeliverWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Scopes\CompanyScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicWebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function dispatch(?int $companyId, string $type, array $data = []): ?string
    {
        if (! $companyId || ! in_array($type, config('public-api.webhook.events', []), true)) {
            return null;
        }

        if (! $this->companyHasEndpoints($companyId)) {
            return null;
        }

        $eventId = 'evt_'.Str::lower(Str::ulid());
        $payload = [
            'id' => $eventId,
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'api_version' => config('public-api.version', 'v1'),
            'company_id' => $companyId,
            'data' => $data,
        ];

        $endpoints = WebhookEndpoint::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        foreach ($endpoints as $endpoint) {
            if (! $endpoint->listensFor($type)) {
                continue;
            }

            $delivery = WebhookDelivery::withoutGlobalScope(CompanyScope::class)->create([
                'company_id' => $companyId,
                'webhook_endpoint_id' => $endpoint->id,
                'event_id' => $eventId,
                'event_type' => $type,
                'payload' => $payload,
                'status' => WebhookDelivery::STATUS_PENDING,
                'attempts' => 0,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }

        return $eventId;
    }

    private function companyHasEndpoints(int $companyId): bool
    {
        return Cache::remember('public-api:webhooks:'.$companyId, 60, function () use ($companyId) {
            return WebhookEndpoint::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->exists();
        });
    }
}
