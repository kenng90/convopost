<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\ManagedAiUsageLog;
use App\Models\Plans;
use App\Models\User;
use App\Services\Billing\CreditCostService;
use Illuminate\Support\Facades\Log;

class ManagedAiService
{
    public function __construct(
        private readonly CreditCostService $creditCostService,
    ) {
    }

    public function actionCost(string $action): int
    {
        return (int) ceil($this->creditCostService->getActionCost($action));
    }

    public function monthlyAllowance(Company $company): int
    {
        return $this->planAllowance($company) + $this->bonusCredits($this->resolveOwner($company));
    }

    public function planAllowance(Company $company): int
    {
        $plan = $this->resolvePlan($company);
        if (! $plan) {
            return (int) config('managed-ai.default_monthly_credits', 0);
        }

        $fromPlan = $plan->getConfig('managed_ai_monthly_credits', null);
        if ($fromPlan !== null && $fromPlan !== '') {
            return (int) $fromPlan;
        }

        return (int) config('managed-ai.default_monthly_credits', 0);
    }

    public function bonusCredits(User $owner): int
    {
        return max(0, (int) $owner->getConfig('managed_ai_bonus_credits', '0'));
    }

    public function grantBonusCredits(User $owner, int $amount): int
    {
        if ($amount <= 0) {
            return $this->bonusCredits($owner);
        }

        $total = $this->bonusCredits($owner) + $amount;
        $owner->setConfig('managed_ai_bonus_credits', (string) $total);

        return $total;
    }

    public function remainingCredits(Company $company): int
    {
        $owner = $this->resolveOwner($company);
        $used = (int) $owner->getConfig('managed_ai_credits_used', '0');
        $allowance = $this->monthlyAllowance($company);

        return max(0, $allowance - $used);
    }

    public function hasByokOpenRouter(Company $company): bool
    {
        return filled($company->getConfig('openrouter_api_key', ''));
    }

    public function usesPlatformOpenRouter(Company $company): bool
    {
        return ! $this->hasByokOpenRouter($company) && filled($this->platformOpenRouterKey());
    }

    public function usesPlatformOpenAi(Company $company): bool
    {
        return filled($this->platformOpenAiKey());
    }

    /**
     * @return array{key: string|null, source: 'byok'|'platform'|'none', should_meter: bool}
     */
    public function resolveOpenRouterKey(Company $company): array
    {
        $byok = $company->getConfig('openrouter_api_key', '');
        if (filled($byok)) {
            return [
                'key' => $byok,
                'source' => 'byok',
                'should_meter' => false,
            ];
        }

        $platform = $this->platformOpenRouterKey();
        if (filled($platform)) {
            return [
                'key' => $platform,
                'source' => 'platform',
                'should_meter' => true,
            ];
        }

        return [
            'key' => null,
            'source' => 'none',
            'should_meter' => false,
        ];
    }

    public function canConsume(Company $company, int $cost = 1): bool
    {
        if (! config('managed-ai.enabled', true)) {
            return true;
        }

        if ($this->hasByokOpenRouter($company)) {
            return true;
        }

        if ($this->monthlyAllowance($company) <= 0) {
            return filled($this->platformOpenRouterKey());
        }

        return $this->remainingCredits($company) >= $cost;
    }

    public function canPerformAction(Company $company, string $action): bool
    {
        $keyInfo = $this->resolveOpenRouterKey($company);

        if ($keyInfo['source'] === 'none') {
            return false;
        }

        if (! $keyInfo['should_meter']) {
            return true;
        }

        return $this->canConsume($company, $this->actionCost($action));
    }

    public function consume(Company $company, int $cost, string $action, array $metadata = []): void
    {
        if (! config('managed-ai.enabled', true) || $cost <= 0) {
            return;
        }

        if ($this->hasByokOpenRouter($company)) {
            return;
        }

        if ($this->planAllowance($company) <= 0 && $this->bonusCredits($this->resolveOwner($company)) <= 0) {
            return;
        }

        $owner = $this->resolveOwner($company);
        $used = (int) $owner->getConfig('managed_ai_credits_used', '0');
        $owner->setConfig('managed_ai_credits_used', (string) ($used + $cost));

        try {
            ManagedAiUsageLog::create([
                'user_id' => $owner->id,
                'company_id' => $company->id,
                'action' => $action,
                'credits' => $cost,
                'used_platform_key' => true,
                'model' => $metadata['model'] ?? null,
                'metadata' => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to write managed AI usage log', ['error' => $e->getMessage()]);
        }
    }

    public function resetIfNewPeriod(Company $company): void
    {
        $owner = $this->resolveOwner($company);
        $this->migrateLegacyCompanyCredits($company, $owner);

        $period = $this->resolveBillingPeriodKey($owner, $this->resolvePlan($company));
        $stored = $owner->getConfig('managed_ai_period', '');

        if ($stored !== $period) {
            // Mid-cycle AI top-ups apply only to the current billing period.
            $owner->setMultipleConfig([
                'managed_ai_period' => $period,
                'managed_ai_credits_used' => '0',
                'managed_ai_bonus_credits' => '0',
            ]);
        }
    }

    public function status(Company $company): array
    {
        $this->resetIfNewPeriod($company);
        $generateCost = $this->actionCost('ai_flow_generate');
        $keyInfo = $this->resolveOpenRouterKey($company);
        $allowance = $this->monthlyAllowance($company);
        $remaining = $this->remainingCredits($company);
        $owner = $this->resolveOwner($company);
        $used = (int) $owner->getConfig('managed_ai_credits_used', '0');

        return [
            'enabled' => (bool) config('managed-ai.enabled', true),
            'monthly_allowance' => $allowance,
            'plan_allowance' => $this->planAllowance($company),
            'bonus_credits' => $this->bonusCredits($owner),
            'remaining' => $remaining,
            'used' => min($used, $allowance),
            'has_own_key' => $this->hasByokOpenRouter($company),
            'uses_platform_key' => $keyInfo['source'] === 'platform',
            'generate_cost' => $generateCost,
            'can_generate' => $this->canPerformAction($company, 'ai_flow_generate'),
            'period_key' => $this->resolveBillingPeriodKey($owner, $this->resolvePlan($company)),
        ];
    }

    public function exhaustionMessage(Company $company): string
    {
        if ($this->hasByokOpenRouter($company)) {
            return __('Unable to generate a draft right now. Check your OpenRouter key or try again shortly.');
        }

        if ($this->monthlyAllowance($company) <= 0) {
            return __('Managed AI is not included on your plan. Upgrade to Pro or add your own OpenRouter API key in workspace settings.');
        }

        return __('AI credits exhausted for this billing period. Upgrade your plan or wait until your allowance resets.');
    }

    public function platformOpenRouterKey(): ?string
    {
        $key = config('managed-ai.platform_openrouter_api_key');

        return filled($key) ? $key : null;
    }

    public function platformOpenAiKey(): ?string
    {
        $key = config('managed-ai.platform_openai_api_key');
        if (filled($key)) {
            return $key;
        }

        $wpboxKey = config('wpbox.openai_api_key');

        return filled($wpboxKey) ? $wpboxKey : null;
    }

    private function resolveOwner(Company $company): User
    {
        $owner = $company->user;
        if (! $owner) {
            throw new \RuntimeException('Company has no owner user.');
        }

        return $owner;
    }

    private function resolvePlan(Company $company): ?Plans
    {
        $user = $company->user;
        if (! $user) {
            return null;
        }

        return Plans::withTrashed()->find($user->mplanid());
    }

    private function migrateLegacyCompanyCredits(Company $company, User $owner): void
    {
        $companyUsed = (int) $company->getConfig('managed_ai_credits_used', '0');
        $ownerUsed = (int) $owner->getConfig('managed_ai_credits_used', '0');

        if ($companyUsed > $ownerUsed) {
            $owner->setConfig('managed_ai_credits_used', (string) $companyUsed);
        }

        if ($companyUsed > 0) {
            $company->setConfig('managed_ai_credits_used', '0');
        }

        $companyPeriod = $company->getConfig('managed_ai_period', '');
        $ownerPeriod = $owner->getConfig('managed_ai_period', '');

        if ($companyPeriod !== '' && $ownerPeriod === '') {
            $owner->setConfig('managed_ai_period', $companyPeriod);
            $company->setConfig('managed_ai_period', '');
        }
    }

    private function resolveBillingPeriodKey(User $owner, ?Plans $plan): string
    {
        if (method_exists($owner, 'subscription')) {
            $subscription = $owner->subscription('default');
            if ($subscription && $subscription->valid()) {
                try {
                    $stripeSubscription = $subscription->asStripeSubscription();

                    return 'stripe_'.$stripeSubscription->current_period_start;
                } catch (\Throwable $e) {
                    Log::debug('Could not read Stripe subscription period for managed AI reset', [
                        'user_id' => $owner->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($plan && (int) $plan->period === 2) {
            return now()->format('Y');
        }

        return now()->format('Y-m');
    }
}
