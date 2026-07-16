<?php

namespace App\Services\WhatsappFlows;

use App\Models\Company;
use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowDynamicDataBuilder;
use Modules\Flowmaker\Models\Contact;

class WhatsappFlowScreenInitService
{
    public function __construct(
        private readonly WhatsappFlowDataExchangeRegistry $registry,
        private readonly WhatsappFlowDynamicDataBuilder $dynamicDataBuilder,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function initDataForScreen(?WhatsappFlow $flow, string $screenId, ?string $flowToken = null): array
    {
        if (! $flow) {
            return [];
        }

        $screens = $flow->flow_json['screens'] ?? [];
        $screen = collect($screens)->firstWhere('id', $screenId) ?? ($screens[0] ?? null);

        if (! is_array($screen)) {
            return [];
        }

        $company = $flow->company_id ? Company::find($flow->company_id) : null;
        $endpointTemplate = $this->registry->resolveEndpointTemplate($flow, $screen);
        $initData = [];

        if ($endpointTemplate !== null) {
            $handler = $this->registry->get($endpointTemplate);
            if ($handler) {
                $context = WhatsappFlowDataExchangeContext::forInit($flow, $company, $screenId, $endpointTemplate, $flowToken);
                $initData = array_merge($initData, $handler->initData($context));
            }
        }

        $initData = array_merge($initData, $this->resolveStoredPrefill($flowToken));

        $dynamicEntries = $screen['dynamic_data'] ?? [];
        if ($dynamicEntries === []) {
            return $initData;
        }

        $schema = $this->dynamicDataBuilder->entriesToMetaSchema($dynamicEntries);

        foreach ($schema as $key => $definition) {
            if (array_key_exists($key, $initData)) {
                continue;
            }

            if (($definition['type'] ?? null) === 'array') {
                $initData[$key] = $definition['__example__'] ?? [];
            } else {
                $initData[$key] = $definition['__example__'] ?? '';
            }
        }

        return $initData;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStoredPrefill(?string $flowToken): array
    {
        if (! $flowToken) {
            return [];
        }

        $response = \App\Models\WhatsappFlowResponse::query()
            ->where('flow_token', $flowToken)
            ->first();

        if (! $response || ! $response->contact_id || ! $response->flow_id) {
            return [];
        }

        $contact = Contact::find($response->contact_id);
        if (! $contact) {
            return [];
        }

        $raw = $contact->getContactStateValue((int) $response->flow_id, 'whatsapp_flow_prefill');
        if (! $raw) {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }
}
