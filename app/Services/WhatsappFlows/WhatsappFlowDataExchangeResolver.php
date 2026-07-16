<?php

namespace App\Services\WhatsappFlows;

use App\Models\Company;
use App\Models\WhatsappFlow;

class WhatsappFlowDataExchangeResolver
{
    public function __construct(
        private readonly WhatsappFlowDataExchangeRegistry $registry,
        private readonly SequentialScreenDataExchangeRouter $sequentialRouter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{screen: string, data: object|array<string, mixed>}
     */
    public function resolve(?WhatsappFlow $flow, ?string $screenId, array $data, ?string $flowToken = null): array
    {
        if (! $flow || ! $screenId) {
            return $this->sequentialRouter->route($flow, $screenId ?? '', $data, $flowToken);
        }

        $screens = $flow->flow_json['screens'] ?? [];
        $screenData = collect($screens)->firstWhere('id', $screenId);

        if (! is_array($screenData)) {
            return $this->sequentialRouter->route($flow, $screenId, $data, $flowToken);
        }

        $company = $flow->company_id ? Company::find($flow->company_id) : null;
        $endpointTemplate = $this->registry->resolveEndpointTemplate($flow, $screenData);

        if ($endpointTemplate !== null) {
            $handler = $this->registry->get($endpointTemplate);
            if ($handler) {
                $context = WhatsappFlowDataExchangeContext::fromExchange(
                    $flow,
                    $company,
                    $screenId,
                    $endpointTemplate,
                    $data,
                    $flowToken,
                );

                $handlerResponse = $handler->handle($context);
                if ($handlerResponse !== null) {
                    return $handlerResponse;
                }
            }
        }

        return $this->sequentialRouter->route($flow, $screenId, $data, $flowToken);
    }
}
