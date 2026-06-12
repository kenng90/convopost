<?php

namespace App\Services\Billing;

use App\Models\Cost;
use Illuminate\Support\Facades\Cache;

class CreditCostService
{
    public function __construct(
        private readonly CreditActionRegistry $registry,
    ) {
    }

    public function flushCache(): void
    {
        Cache::forget('credit_action_costs');
    }

    /**
     * Fixed credit cost for an action. Returns 0 for free actions.
     * Usage-based actions (-1) fall back to $usageAmount or 1.
     */
    public function getActionCost(string $action, int|float $usageAmount = 1): float
    {
        if (! config('settings.enable_credits', false)) {
            return 0;
        }

        $stored = $this->storedCosts()[$action] ?? null;

        if ($stored === null) {
            $definition = $this->registry->find($action);

            return max(0, (float) ($definition['default_cost'] ?? 0));
        }

        if ((float) $stored === -1.0) {
            return max(0, (float) $usageAmount);
        }

        return max(0, (float) $stored);
    }

    public function isUsageBased(string $action): bool
    {
        $stored = $this->storedCosts()[$action] ?? null;

        if ($stored === null) {
            return false;
        }

        return (float) $stored === -1.0;
    }

    /**
     * @return array<int, array{action: string, name: string, category: string, cost: float, default_cost: int, help: string, module: string, sort_order: int, is_usage_based: bool}>
     */
    public function actionsForAdmin(): array
    {
        $stored = $this->storedCosts();
        $actions = [];

        foreach ($this->registry->definitions() as $definition) {
            $action = $definition['action'];
            $storedCost = $stored[$action] ?? null;
            $isUsageBased = $storedCost !== null && (float) $storedCost === -1.0;
            $effectiveCost = $storedCost !== null && ! $isUsageBased
                ? (float) $storedCost
                : (float) $definition['default_cost'];

            $actions[] = array_merge($definition, [
                'cost' => $effectiveCost,
                'is_usage_based' => $isUsageBased,
            ]);
        }

        return $actions;
    }

    /**
     * @return array<string, float|int>
     */
    private function storedCosts(): array
    {
        return Cache::remember('credit_action_costs', 300, function () {
            return Cost::query()->pluck('cost', 'action')->all();
        });
    }
}
