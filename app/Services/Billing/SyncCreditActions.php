<?php

namespace App\Services\Billing;

use App\Models\Cost;

class SyncCreditActions
{
    public function __construct(
        private readonly CreditActionRegistry $registry,
        private readonly CreditCostService $costs,
    ) {
    }

    public function sync(bool $onlyMissing = true): int
    {
        $created = 0;

        foreach ($this->registry->definitions() as $definition) {
            $existing = Cost::query()->where('action', $definition['action'])->first();

            if ($existing !== null) {
                if (! $onlyMissing) {
                    $existing->update($this->metadataPayload($definition));
                }

                continue;
            }

            Cost::query()->create(array_merge(
                $this->metadataPayload($definition),
                ['cost' => $definition['default_cost']],
            ));

            $created++;
        }

        $this->costs->flushCache();

        return $created;
    }

    /**
     * @param  array{action: string, name: string, category: string, default_cost: int, help: string, module: string, sort_order: int}  $definition
     * @return array<string, mixed>
     */
    private function metadataPayload(array $definition): array
    {
        return [
            'action' => $definition['action'],
            'label' => $definition['name'],
            'category' => $definition['category'],
            'default_cost' => $definition['default_cost'],
            'help' => $definition['help'],
            'module' => $definition['module'],
            'sort_order' => $definition['sort_order'],
            'is_active' => true,
        ];
    }
}
