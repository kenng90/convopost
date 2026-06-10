<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Credit;
use App\Models\Plans;
use App\Models\User;
use Carbon\Carbon;

class PlanCreditAllocator
{
    public function grantForUser(User $user, Plans $plan, ?Carbon $issuedAt = null): bool
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
            ->where('user_id', $user->id)
            ->where('source', $source)
            ->exists();

        if ($alreadyGranted) {
            return false;
        }

        $expirationDate = (clone $issuedAt)->addDays($plan->period == 1 ? 30 : 365);
        $user->addCredits($amount, $source, $expirationDate);

        return true;
    }

    public function replacePlanCreditsForUser(User $user, Plans $plan, ?Carbon $issuedAt = null): bool
    {
        if (! config('settings.enable_credits', true)) {
            return false;
        }

        Credit::query()
            ->where('user_id', $user->id)
            ->where('source', 'like', 'plan:%')
            ->delete();

        return $this->grantForUser($user, $plan, $issuedAt);
    }

    /**
     * @deprecated Use grantForUser() — credits are shared at the owner account level.
     */
    public function grantForCompany(Company $company, Plans $plan, ?Carbon $issuedAt = null): bool
    {
        if ($company->user === null) {
            return false;
        }

        return $this->grantForUser($company->user, $plan, $issuedAt);
    }

    /**
     * @deprecated Use replacePlanCreditsForUser() — credits are shared at the owner account level.
     */
    public function replacePlanCreditsForCompany(Company $company, Plans $plan, ?Carbon $issuedAt = null): bool
    {
        if ($company->user === null) {
            return false;
        }

        return $this->replacePlanCreditsForUser($company->user, $plan, $issuedAt);
    }

    public function buildGrantSource(Plans $plan, Carbon $issuedAt): string
    {
        return sprintf('plan:%d:%s', $plan->id, $issuedAt->format('Y-m'));
    }
}
