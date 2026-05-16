<?php

namespace App\Services;

use App\Models\CatalogItemUsage;
use App\Models\Company;
use App\Models\Plans;
use Carbon\Carbon;

class CatalogItemPlanLimit
{
    public function resolvePlanForCompany(Company $company): Plans
    {
        $plan = Plans::withTrashed()->find($company->user->mplanid());

        if ($plan === null) {
            $plan = new Plans();
            $plan->limit_catalog_items = 0;
            $plan->period = 1;
        }

        return $plan;
    }

    public function getAllowedLimit(Plans $plan): int
    {
        $limit = (int) ($plan->limit_catalog_items ?? 0);

        if ($limit <= 0) {
            return 0;
        }

        return $plan->period == 2 ? $limit * 12 : $limit;
    }

    public function getPeriodDays(Plans $plan): int
    {
        return $plan->period == 2 ? 365 : 30;
    }

    public function usageInPeriod(int $companyId, Plans $plan): int
    {
        return (int) CatalogItemUsage::query()
            ->where('company_id', $companyId)
            ->where('created_at', '>=', Carbon::now()->subDays($this->getPeriodDays($plan)))
            ->sum('quantity');
    }

    public function isUnlimited(Plans $plan): bool
    {
        return $this->getAllowedLimit($plan) <= 0;
    }

    public function canAdd(Company $company, int $additional = 1): bool
    {
        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimit($plan);

        if ($limit <= 0) {
            return true;
        }

        return ($this->usageInPeriod($company->id, $plan) + $additional) <= $limit;
    }

    /**
     * @return array{used: int, limit: int, unlimited: bool, remaining: int|null}
     */
    public function getUsageSummary(Company $company): array
    {
        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimit($plan);
        $used = $this->usageInPeriod($company->id, $plan);

        return [
            'used' => $used,
            'limit' => $limit,
            'unlimited' => $limit <= 0,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
        ];
    }

    public function recordUsage(int $companyId, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        CatalogItemUsage::create([
            'company_id' => $companyId,
            'quantity' => $quantity,
        ]);
    }

    public function limitExceededMessage(Company $company, int $additional = 1): string
    {
        $summary = $this->getUsageSummary($company);

        if ($summary['unlimited']) {
            return '';
        }

        return __('You have reached your catalog item limit for this plan period (:used of :limit). Upgrade your plan or wait for the next billing period.', [
            'used' => $summary['used'],
            'limit' => $summary['limit'],
        ]);
    }
}
