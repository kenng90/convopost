<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\Plans;

class ManagedAiService
{
    public function monthlyAllowance(Company $company): int
    {
        $plan = $this->resolvePlan($company);
        if (! $plan) {
            return (int) config('managed-ai.default_monthly_credits', 0);
        }

        $tier = $this->resolveTierKey($plan);

        return (int) config("managed-ai.tiers.{$tier}.monthly_credits", config('managed-ai.default_monthly_credits', 0));
    }

    public function remainingCredits(Company $company): int
    {
        $used = (int) $company->getConfig('managed_ai_credits_used', '0');
        $allowance = $this->monthlyAllowance($company);

        return max(0, $allowance - $used);
    }

    public function canConsume(Company $company, int $cost = 1): bool
    {
        if (! config('managed-ai.enabled', true)) {
            return true;
        }

        if ($this->monthlyAllowance($company) <= 0) {
            return $company->getConfig('openrouter_api_key', '') !== ''
                || config('wpbox.openai_api_key', '') !== '';
        }

        return $this->remainingCredits($company) >= $cost;
    }

    public function consume(Company $company, int $cost = 1): void
    {
        if (! config('managed-ai.enabled', true) || $this->monthlyAllowance($company) <= 0) {
            return;
        }

        $used = (int) $company->getConfig('managed_ai_credits_used', '0');
        $company->setConfig('managed_ai_credits_used', (string) ($used + $cost));
    }

    public function resetIfNewPeriod(Company $company): void
    {
        $period = now()->format('Y-m');
        $stored = $company->getConfig('managed_ai_period', '');

        if ($stored !== $period) {
            $company->setMultipleConfig([
                'managed_ai_period' => $period,
                'managed_ai_credits_used' => '0',
            ]);
        }
    }

    public function status(Company $company): array
    {
        $this->resetIfNewPeriod($company);

        return [
            'enabled' => (bool) config('managed-ai.enabled', true),
            'monthly_allowance' => $this->monthlyAllowance($company),
            'remaining' => $this->remainingCredits($company),
            'used' => (int) $company->getConfig('managed_ai_credits_used', '0'),
            'has_own_key' => filled($company->getConfig('openrouter_api_key', '')),
        ];
    }

    private function resolvePlan(Company $company): ?Plans
    {
        $user = $company->user;
        if (! $user) {
            return null;
        }

        return Plans::withTrashed()->find($user->mplanid());
    }

    private function resolveTierKey(Plans $plan): string
    {
        $name = strtolower($plan->name ?? '');

        foreach (['agency', 'pro', 'growth', 'starter'] as $tier) {
            if (str_contains($name, $tier)) {
                return $tier;
            }
        }

        return 'starter';
    }
}
