<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;

class PlanResourceLimit
{
    public const SHOPIFY_KEYS = ['shopify_store_name', 'shopify_access_token'];

    public const WOOCOMMERCE_KEYS = ['woocommerce_store_url', 'woocommerce_consumer_key'];

    public function __construct(private readonly PlanUsageLimit $planUsageLimit)
    {
    }

    public function resolvePlanForCompany(Company $company): Plans
    {
        return $this->planUsageLimit->resolvePlanForCompany($company);
    }

    public function resolvePlanForUser(User $user): ?Plans
    {
        return $this->planUsageLimit->resolvePlanForUser($user);
    }

    /**
     * @return array{agents: int, companies: int, integrations: int}
     */
    public function getAllowedLimits(Plans $plan): array
    {
        return [
            'agents' => (int) ($plan->limit_agents ?? 0),
            'companies' => (int) ($plan->limit_companies ?? 0),
            'integrations' => (int) ($plan->limit_integrations ?? 0),
        ];
    }

    public function isUnlimited(int $limit): bool
    {
        return $limit <= 0;
    }

    public function countAgents(Company $company): int
    {
        return (int) $company->staff()->count();
    }

    public function countTotalAgentsForOwner(User $owner): int
    {
        $companyIds = Company::query()
            ->where('user_id', $owner->id)
            ->pluck('id');

        if ($companyIds->isEmpty()) {
            return 0;
        }

        return (int) User::query()
            ->whereIn('company_id', $companyIds)
            ->whereHas('roles', fn ($query) => $query->where('name', 'staff'))
            ->count();
    }

    public function countCompanies(User $owner): int
    {
        return (int) Company::query()
            ->where('user_id', $owner->id)
            ->count();
    }

    public function countConnectedIntegrations(Company $company): int
    {
        return $this->countIntegrationsFromConfigs($company->getAllConfigs());
    }

    public function countIntegrationsFromConfigs(array $configs): int
    {
        $count = 0;

        if ($this->isShopifyConnectedFromConfigs($configs)) {
            $count++;
        }

        if ($this->isWooCommerceConnectedFromConfigs($configs)) {
            $count++;
        }

        return $count;
    }

    public function isShopifyConnectedFromConfigs(array $configs): bool
    {
        return $this->hasNonEmptyConfigValues($configs, self::SHOPIFY_KEYS);
    }

    public function isWooCommerceConnectedFromConfigs(array $configs): bool
    {
        return $this->hasNonEmptyConfigValues($configs, self::WOOCOMMERCE_KEYS);
    }

    public function canAddAgent(Company $company): bool
    {
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            return true;
        }

        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimits($plan)['agents'];

        if ($this->isUnlimited($limit)) {
            return true;
        }

        return $this->countAgents($company) < $limit;
    }

    public function canAddCompany(User $owner): bool
    {
        if ($owner->hasRole('admin')) {
            return true;
        }

        $plan = $this->resolvePlanForUser($owner);

        if ($plan === null) {
            return true;
        }

        $limit = $this->getAllowedLimits($plan)['companies'];

        if ($this->isUnlimited($limit)) {
            return true;
        }

        return $this->countCompanies($owner) < $limit;
    }

    public function validateIntegrationConfigUpdate(Company $company, array $incomingConfigs): ?string
    {
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            return null;
        }

        if (! $this->touchesIntegrationKeys($incomingConfigs)) {
            return null;
        }

        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimits($plan)['integrations'];

        if ($this->isUnlimited($limit)) {
            return null;
        }

        $mergedConfigs = array_merge($company->getAllConfigs(), $incomingConfigs);
        $afterCount = $this->countIntegrationsFromConfigs($mergedConfigs);

        if ($afterCount <= $limit) {
            return null;
        }

        return __('You have reached your store integration limit (:used of :limit). Disconnect an integration or upgrade your plan.', [
            'used' => $afterCount,
            'limit' => $limit,
        ]);
    }

    public function agentLimitExceededMessage(Company $company): string
    {
        $plan = $this->resolvePlanForCompany($company);
        $limit = $this->getAllowedLimits($plan)['agents'];

        return __('You have reached your agent seat limit (:used of :limit). Remove an agent or upgrade your plan.', [
            'used' => $this->countAgents($company),
            'limit' => $limit,
        ]);
    }

    public function companyLimitExceededMessage(User $owner): string
    {
        $plan = $this->resolvePlanForUser($owner);
        $limit = $plan ? $this->getAllowedLimits($plan)['companies'] : 0;

        return __('You have reached your organization limit (:used of :limit). Upgrade your plan to add more WhatsApp numbers.', [
            'used' => $this->countCompanies($owner),
            'limit' => $limit,
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string, used: int, limit: int, unlimited: bool, remaining: int|null, alert: string}>
     */
    public function getUsageSummary(Company $company): array
    {
        $plan = $this->resolvePlanForCompany($company);
        $allowed = $this->getAllowedLimits($plan);
        $labels = config('plan-entitlements.resource_limit_labels', []);
        $summary = [];

        foreach ([
            'agents' => $this->countAgents($company),
            'companies' => $this->countCompanies($company->user),
            'integrations' => $this->countConnectedIntegrations($company),
        ] as $key => $used) {
            $limit = $allowed[$key];
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

        return $summary;
    }

    private function touchesIntegrationKeys(array $configs): bool
    {
        return count(array_intersect(array_keys($configs), array_merge(self::SHOPIFY_KEYS, self::WOOCOMMERCE_KEYS))) > 0;
    }

    private function hasNonEmptyConfigValues(array $configs, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! isset($configs[$key]) || trim((string) $configs[$key]) === '') {
                return false;
            }
        }

        return true;
    }
}
