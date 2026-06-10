<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanUsageLimit
{
    public function resolvePlanForUser(User $user): ?Plans
    {
        if ($user->hasRole('admin')) {
            return null;
        }

        if ($user->hasRole('owner')) {
            return Plans::find($user->mplanid());
        }

        $owner = $user->company?->user;

        return $owner ? Plans::find($owner->mplanid()) : null;
    }

    public function resolvePlanForCompany(Company $company): Plans
    {
        $plan = Plans::withTrashed()->find($company->user->mplanid());

        if ($plan === null) {
            $plan = new Plans();
            $plan->limit_items = 0;
            $plan->limit_orders = 0;
            $plan->limit_views = 0;
            $plan->limit_catalog_items = 0;
            $plan->limit_agents = 0;
            $plan->limit_companies = 0;
            $plan->limit_integrations = 0;
            $plan->period = 1;
        }

        return $plan;
    }

    public function getPeriodDays(Plans $plan): int
    {
        return $plan->period == 2 ? 365 : 30;
    }

    /**
     * @return array{campaigns: int, messages: int, contacts: int}
     */
    public function getAllowedLimits(Plans $plan): array
    {
        $multiplier = $plan->period == 2 ? 12 : 1;

        return [
            'campaigns' => (int) $plan->limit_items * $multiplier,
            'messages' => (int) $plan->limit_views * $multiplier,
            'contacts' => (int) $plan->limit_orders * $multiplier,
        ];
    }

    /**
     * @return array{campaigns: int, messages: int, contacts: int}
     */
    public function getUsageForCompany(Company $company, Plans $plan): array
    {
        $days = $this->getPeriodDays($plan);
        $since = Carbon::now()->subDays($days);

        return [
            'campaigns' => (int) DB::table('wa_campaings')
                ->where('created_at', '>=', $since)
                ->where('company_id', $company->id)
                ->count(),
            'messages' => (int) DB::table('messages')
                ->where('created_at', '>=', $since)
                ->where('company_id', $company->id)
                ->whereNotNull('fb_message_id')
                ->count(),
            'contacts' => (int) DB::table('contacts')
                ->where('company_id', $company->id)
                ->count(),
        ];
    }

    public function isUnlimited(int $limit): bool
    {
        return $limit <= 0;
    }

    /**
     * Returns the first exceeded limit key, or null when within limits.
     */
    public function firstExceededLimit(Company $company, Plans $plan): ?string
    {
        $allowed = $this->getAllowedLimits($plan);
        $usage = $this->getUsageForCompany($company, $plan);

        foreach (['messages', 'campaigns', 'contacts'] as $key) {
            if (! $this->isUnlimited($allowed[$key]) && $usage[$key] >= $allowed[$key]) {
                return $key;
            }
        }

        return null;
    }

    public function exceededMessage(string $limitKey): string
    {
        return match ($limitKey) {
            'messages' => __('You have exceeded the limit of messages allowed in your plan'),
            'campaigns' => __('You have exceeded the limit of campaigns allowed in your plan'),
            'contacts' => __('You have exceeded the limit of contacts allowed in your plan'),
            default => __('You have exceeded a limit on your current plan'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, used: int, limit: int, unlimited: bool, remaining: int|null, alert: string}>
     */
    public function getUsageSummary(Company $company): array
    {
        $plan = $this->resolvePlanForCompany($company);
        $allowed = $this->getAllowedLimits($plan);
        $usage = $this->getUsageForCompany($company, $plan);
        $labels = config('plan-entitlements.limit_labels', []);
        $summary = [];

        foreach (['campaigns', 'messages', 'contacts'] as $key) {
            $limit = $allowed[$key];
            $used = $usage[$key];
            $unlimited = $this->isUnlimited($limit);

            $summary[] = [
                'key' => $key,
                'label' => $labels[$key] ?? ucfirst($key),
                'used' => $used,
                'limit' => $limit,
                'unlimited' => $unlimited,
                'remaining' => $unlimited ? null : max(0, $limit - $used),
                'alert' => (! $unlimited && $used >= $limit) ? 'warning' : 'info',
            ];
        }

        return array_merge($summary, app(PlanResourceLimit::class)->getUsageSummary($company));
    }
}
