<?php

namespace App\Services\WhatsappFlows;

use App\Contracts\WhatsappFlowDataExchangeHandler;

class DynamicOptionsDataExchangeHandler implements WhatsappFlowDataExchangeHandler
{
    public function __construct(
        private readonly WhatsappFlowScreenInitService $screenInitService,
    ) {
    }

    public function keys(): array
    {
        return ['dynamic_options'];
    }

    public function formBundleKeys(): array
    {
        return [];
    }

    public function handle(WhatsappFlowDataExchangeContext $context): ?array
    {
        if (! $context->flow) {
            return null;
        }

        return [
            'screen' => $context->screenId,
            'data' => (object) $this->screenInitService->initDataForScreen($context->flow, $context->screenId),
        ];
    }

    public function initData(WhatsappFlowDataExchangeContext $context): array
    {
        return [];
    }
}
