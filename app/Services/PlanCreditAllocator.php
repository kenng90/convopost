<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Credit;
use App\Models\Plans;
use Carbon\Carbon;

class PlanCreditAllocator
{
    public function grantForCompany(Company $company, Plans $plan, ?Carbon $issuedAt = null): bool
    {
        if (! config('settings.enable_credits', true)) {
            return false;
        }

        $amount = (float) $plan->credit_amount;
        if ($amount <= 0) {
            return false;
        }

        $issuedAt ??= Carbon::now();
        $source = $this->buildGrantSource($plan, $issuedAt);

        $alreadyGranted = Credit::query()
            ->where('company_id', $company->id)
            ->where('source', $source)
            ->exists();

        if ($alreadyGranted) {
            return false;
        }

        $expirationDate = (clone $issuedAt)->addDays($plan->period == 1 ? 30 : 365);
        $company->addCredits($amount, $source, $expirationDate);

        return true;
    }

    /**
     * Replace plan-sourced credits when the owner changes subscription tier.
     */
    public function replacePlanCreditsForCompany(Company $company, Plans $plan, ?Carbon $issuedAt = null): bool
    {
        if (! config('settings.enable_credits', true)) {
            return false;
        }

        Credit::query()
            ->where('company_id', $company->id)
            ->where('source', 'like', 'plan:%')
            ->delete();

        return $this->grantForCompany($company, $plan, $issuedAt);
    }

    public function buildGrantSource(Plans $plan, Carbon $issuedAt): string
    {
        return sprintf('plan:%d:%s', $plan->id, $issuedAt->format('Y-m'));
    }
}
