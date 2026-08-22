<?php

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\PlatformEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Models\IntegrationConnector;

class PlatformEventBus
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function emit(Company $company, string $event, array $payload = []): PlatformEvent
    {
        $body = array_merge($payload, [
            'event' => $event,
            'company_id' => $company->id,
            'occurred_at' => now()->toIso8601String(),
        ]);
        $encoded = json_encode($body) ?: '{}';
        $secret = (string) $company->getConfig('plain_token', config('app.key'));
        $signature = hash_hmac('sha256', $encoded, $secret);

        $record = PlatformEvent::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'event' => $event,
            'payload' => $body,
            'signature' => $signature,
            'status' => 'pending',
        ]);

        $this->deliver($company, $record);

        return $record->fresh();
    }

    public function deliver(Company $company, PlatformEvent $event): void
    {
        $connectors = IntegrationConnector::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('status', 'connected')
            ->get();

        $errors = [];

        foreach ($connectors as $connector) {
            try {
                $this->deliverToConnector($connector, $event);
            } catch (\Throwable $e) {
                $errors[] = $connector->provider.': '.$e->getMessage();
                Log::warning('platform_event.deliver_failed', [
                    'provider' => $connector->provider,
                    'event' => $event->event,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $event->status = $errors === [] ? 'delivered' : 'failed';
        $event->delivered_at = $errors === [] ? now() : null;
        $event->last_error = $errors === [] ? null : implode('; ', $errors);
        $event->save();
    }

    private function deliverToConnector(IntegrationConnector $connector, PlatformEvent $event): void
    {
        $credentials = $connector->credentials ?? [];
        $headers = [
            'X-Convocon-Event' => $event->event,
            'X-Convocon-Signature' => $event->signature,
            'Content-Type' => 'application/json',
        ];

        if ($connector->provider === 'zapier' && filled($credentials['webhook_url'] ?? null)) {
            Http::timeout(10)->withHeaders($headers)->post($credentials['webhook_url'], $event->payload);

            return;
        }

        if ($connector->provider === 'google_sheets' && filled($credentials['webhook_url'] ?? null)) {
            Http::timeout(10)->withHeaders($headers)->post($credentials['webhook_url'], $event->payload);

            return;
        }

        if ($connector->provider === 'hubspot' && filled($credentials['api_key'] ?? null)) {
            $email = $event->payload['email'] ?? null;
            if ($email) {
                Http::timeout(10)
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$credentials['api_key'],
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.hubapi.com/crm/v3/objects/contacts', [
                        'properties' => [
                            'email' => $email,
                            'phone' => $event->payload['phone'] ?? null,
                            'firstname' => $event->payload['customer_name'] ?? null,
                        ],
                    ]);
            }

            return;
        }

        if ($connector->provider === 'quickbooks' && filled($credentials['webhook_url'] ?? null)) {
            Http::timeout(10)->withHeaders($headers)->post($credentials['webhook_url'], $event->payload);
        }
    }
}
