<?php

namespace App\Contracts;

use App\Services\WhatsappFlows\WhatsappFlowDataExchangeContext;

interface WhatsappFlowDataExchangeHandler
{
    /**
     * Endpoint template keys this handler serves (screen.endpoint_template).
     *
     * @return list<string>
     */
    public function keys(): array;

    /**
     * Optional form bundle keys for documentation / future defaults.
     *
     * @return list<string>
     */
    public function formBundleKeys(): array;

    /**
     * Handle a data_exchange request. Return null to defer to the next handler or sequential router.
     *
     * @return array{screen: string, data: object|array<string, mixed>}|null
     */
    public function handle(WhatsappFlowDataExchangeContext $context): ?array;

    /**
     * Payload fields for INIT on screens using this handler's endpoint template.
     *
     * @return array<string, mixed>
     */
    public function initData(WhatsappFlowDataExchangeContext $context): array;
}
