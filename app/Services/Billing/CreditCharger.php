<?php

namespace App\Services\Billing;

use App\Models\Company;

class CreditCharger
{
    public function __construct(
        private readonly CreditCostService $costs,
    ) {
    }

    public function canCharge(Company $company, string $action, int|float $usageAmount = 1): bool
    {
        if (! config('settings.enable_credits', false)) {
            return true;
        }

        $amount = $this->costs->getActionCost($action, $usageAmount);

        if ($amount <= 0) {
            return true;
        }

        return $company->hasEnoughCredits($amount);
    }

    public function charge(Company $company, string $action, ?int $companyId = null, int|float $usageAmount = 1): bool
    {
        if (! config('settings.enable_credits', false)) {
            return true;
        }

        $amount = $this->costs->getActionCost($action, $usageAmount);

        if ($amount <= 0) {
            return true;
        }

        return $company->useCredits($amount, $action, $companyId ?? $company->id);
    }

    public function insufficientCreditsMessage(string $action, int|float $usageAmount = 1): string
    {
        $amount = $this->costs->getActionCost($action, $usageAmount);

        if ($amount <= 0) {
            return __('No credits left');
        }

        return __('No credits left. This action requires :cost credits.', ['cost' => $this->formatCredits($amount)]);
    }

    private function formatCredits(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
