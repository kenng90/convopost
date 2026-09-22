<?php

namespace Modules\Social\Services;

use App\Models\Company;
use App\Models\Plans;
use Modules\Social\Models\SocialAccount;

class SocialAccountPlanLimit
{
    public function resolvePlanForCompany(Company $company): Plans
    {
        $plan = Plans::withTrashed()->find($company->user->mplanid());

        if ($plan === null) {
            $plan = new Plans;
            $plan->period = 1;
        }

        return $plan;
    }

    public function getAllowedLimit(Plans $plan): int
    {
        return (int) $plan->getConfig('limit_social_accounts', 0);
    }

    public function isUnlimited(Plans $plan): bool
    {
        return $this->getAllowedLimit($plan) <= 0;
    }

    public function countActiveAccounts(int $companyId): int
    {
        return (int) SocialAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->count();
    }

    public function canAdd(Company $company, int $additional = 1): bool
    {
        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimit($plan);

        if ($limit <= 0) {
            return true;
        }

        return ($this->countActiveAccounts($company->id) + $additional) <= $limit;
    }

    /**
     * @return array{used: int, limit: int, unlimited: bool, remaining: int|null}
     */
    public function getUsageSummary(Company $company): array
    {
        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimit($plan);
        $used = $this->countActiveAccounts($company->id);

        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $limit <= 0,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
        ];
    }

    public function limitExceededMessage(Company $company, int $additional = 1): string
    {
        $summary = $this->getUsageSummary($company);

        if ($summary['unlimited']) {
            return '';
        }

        return __('Your plan allows :limit social account(s). You have :used connected and tried to add :additional more. Upgrade your plan to connect more networks.', [
            'limit' => $summary['limit'],
            'used' => $summary['used'],
            'additional' => $additional,
        ]);
    }
}
