<?php

namespace App\Services\WhatsappFlows;

use App\Contracts\WhatsappFlowDataExchangeHandler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Config-driven data exchange handler for arbitrary HTTP endpoints.
 *
 * Configure in config/whatsapp-flow-data-exchange.php under "http_handlers".
 */
class ConfigurableDataExchangeHandler implements WhatsappFlowDataExchangeHandler
{
    public function keys(): array
    {
        return array_keys(config('whatsapp-flow-data-exchange.http_handlers', []));
    }

    public function formBundleKeys(): array
    {
        return [];
    }

    public function handle(WhatsappFlowDataExchangeContext $context): ?array
    {
        $config = config('whatsapp-flow-data-exchange.http_handlers.'.$context->endpointTemplate);
        if (! is_array($config)) {
            return null;
        }

        $type = $config['type'] ?? 'http';
        if ($type === 'class' && ! empty($config['handler'])) {
            $handler = app($config['handler']);
            if ($handler instanceof WhatsappFlowDataExchangeHandler) {
                return $handler->handle($context);
            }

            return null;
        }

        $url = $config['url'] ?? null;
        if (! is_string($url) || $url === '') {
            return null;
        }

        $method = strtoupper((string) ($config['method'] ?? 'POST'));
        $payload = [
            'flow_id' => $context->flow?->id,
            'meta_flow_id' => $context->flow?->meta_flow_id,
            'screen_id' => $context->screenId,
            'endpoint_template' => $context->endpointTemplate,
            'data' => $context->data,
            'flow_token' => $context->flowToken,
            'company_id' => $context->company?->id,
        ];

        try {
            $request = Http::timeout((int) ($config['timeout'] ?? 15));
            if (! empty($config['headers']) && is_array($config['headers'])) {
                $request = $request->withHeaders($config['headers']);
            }

            $response = match ($method) {
                'GET' => $request->get($url, $payload),
                'PUT' => $request->put($url, $payload),
                'PATCH' => $request->patch($url, $payload),
                default => $request->post($url, $payload),
            };

            if (! $response->successful()) {
                Log::warning('ConfigurableDataExchangeHandler HTTP error', [
                    'endpoint' => $context->endpointTemplate,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->json();
            if (! is_array($body) || empty($body['screen'])) {
                return null;
            }

            return [
                'screen' => (string) $body['screen'],
                'data' => $body['data'] ?? (object) [],
            ];
        } catch (\Throwable $e) {
            Log::error('ConfigurableDataExchangeHandler exception', [
                'endpoint' => $context->endpointTemplate,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function initData(WhatsappFlowDataExchangeContext $context): array
    {
        $config = config('whatsapp-flow-data-exchange.http_handlers.'.$context->endpointTemplate);
        if (! is_array($config)) {
            return [];
        }

        return is_array($config['init_data'] ?? null) ? $config['init_data'] : [];
    }
}
