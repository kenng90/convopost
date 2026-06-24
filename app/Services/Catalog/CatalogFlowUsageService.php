<?php

namespace App\Services\Catalog;

use Modules\Flowmaker\Models\Flow;

class CatalogFlowUsageService
{
    /**
     * @return list<array{id: int, name: string}>
     */
    public function flowsUsingCatalog(int $catalogId, int $companyId): array
    {
        $flows = Flow::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNotNull('flow_data')
            ->get(['id', 'name', 'flow_data']);

        $matches = [];

        foreach ($flows as $flow) {
            if ($this->flowReferencesCatalog($flow->flow_data, $catalogId)) {
                $matches[] = [
                    'id' => $flow->id,
                    'name' => $flow->name,
                ];
            }
        }

        return $matches;
    }

    public function isCatalogInUse(int $catalogId, int $companyId): bool
    {
        return $this->flowsUsingCatalog($catalogId, $companyId) !== [];
    }

    private function flowReferencesCatalog(?string $flowData, int $catalogId): bool
    {
        if (! $flowData || $flowData === '{}' || $flowData === '') {
            return false;
        }

        $decoded = json_decode($flowData, true);
        if (! is_array($decoded)) {
            return false;
        }

        foreach ($decoded['nodes'] ?? [] as $node) {
            if (! in_array($node['type'] ?? '', ['whatsapp_catalog', 'listing_inquiry'], true)) {
                continue;
            }

            $settings = $node['data']['settings'] ?? $node['settings'] ?? [];
            if ((int) ($settings['catalogId'] ?? 0) === $catalogId) {
                return true;
            }
        }

        return false;
    }
}
