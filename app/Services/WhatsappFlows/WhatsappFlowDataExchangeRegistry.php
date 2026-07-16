<?php

namespace App\Services\WhatsappFlows;

use App\Contracts\WhatsappFlowDataExchangeHandler;
use App\Models\WhatsappFlow;

class WhatsappFlowDataExchangeRegistry
{
    /** @var array<string, WhatsappFlowDataExchangeHandler> */
    private array $handlersByKey = [];

    /** @var list<WhatsappFlowDataExchangeHandler> */
    private array $handlers = [];

    public function register(WhatsappFlowDataExchangeHandler $handler): void
    {
        $this->handlers[] = $handler;

        foreach ($handler->keys() as $key) {
            $this->handlersByKey[$key] = $handler;
        }
    }

    public function get(string $key): ?WhatsappFlowDataExchangeHandler
    {
        return $this->handlersByKey[$key] ?? null;
    }

    /**
     * @return list<array{key: string, handler: class-string, bundles: list<string>}>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->handlers as $handler) {
            foreach ($handler->keys() as $key) {
                $definitions[] = [
                    'key' => $key,
                    'handler' => $handler::class,
                    'bundles' => $handler->formBundleKeys(),
                ];
            }
        }

        usort($definitions, fn (array $a, array $b) => strcmp($a['key'], $b['key']));

        return $definitions;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->handlersByKey);
    }

    public function resolveEndpointTemplate(WhatsappFlow $flow, array $screenData): ?string
    {
        $endpointTemplate = $screenData['endpoint_template'] ?? null;
        if (is_string($endpointTemplate) && $endpointTemplate !== '') {
            return $endpointTemplate;
        }

        $screenId = $screenData['id'] ?? null;
        $bundleKey = $flow->form_bundle_key ?? null;

        if (! is_string($screenId) || $screenId === '' || ! is_string($bundleKey) || $bundleKey === '') {
            return null;
        }

        $configured = config("whatsapp-flow-data-exchange.bundle_screen_defaults.{$bundleKey}.{$screenId}");

        return is_string($configured) && $configured !== '' ? $configured : null;
    }
}
