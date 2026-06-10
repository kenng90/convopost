<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Subscription;

class PlanSeatBillingService
{
    public function __construct(private readonly PlanResourceLimit $planResourceLimit)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config('settings.enable_per_seat_billing', false);
    }

    public function billableAgentSeats(User $owner, ?Plans $plan = null): int
    {
        $plan ??= $this->planResourceLimit->resolvePlanForUser($owner);

        if ($plan === null || ! $this->hasAgentSeatPrice($plan)) {
            return 0;
        }

        $included = (int) ($plan->included_agent_seats ?? 0);
        $total = $this->planResourceLimit->countTotalAgentsForOwner($owner);

        return max(0, $total - $included);
    }

    public function billableCompanySeats(User $owner, ?Plans $plan = null): int
    {
        $plan ??= $this->planResourceLimit->resolvePlanForUser($owner);

        if ($plan === null || ! $this->hasCompanySeatPrice($plan)) {
            return 0;
        }

        $included = (int) ($plan->included_companies ?? 0);
        $total = $this->planResourceLimit->countCompanies($owner);

        return max(0, $total - $included);
    }

    public function hasAgentSeatPrice(Plans $plan): bool
    {
        return strlen(trim((string) ($plan->stripe_agent_seat_price_id ?? ''))) > 2;
    }

    public function hasCompanySeatPrice(Plans $plan): bool
    {
        return strlen(trim((string) ($plan->stripe_company_seat_price_id ?? ''))) > 2;
    }

    public function shouldSync(User $owner): bool
    {
        if (! $this->isEnabled() || $owner->hasRole('admin')) {
            return false;
        }

        $plan = $this->planResourceLimit->resolvePlanForUser($owner);

        if ($plan === null) {
            return false;
        }

        if (! $this->hasAgentSeatPrice($plan) && ! $this->hasCompanySeatPrice($plan)) {
            return false;
        }

        return $this->resolveSubscription($owner) !== null;
    }

    public function syncForOwner(User $owner): bool
    {
        if (! $this->shouldSync($owner)) {
            return false;
        }

        $plan = $this->planResourceLimit->resolvePlanForUser($owner);
        $subscription = $this->resolveSubscription($owner);

        if ($plan === null || $subscription === null) {
            return false;
        }

        try {
            $this->syncSeatPrice(
                $subscription,
                $plan->stripe_agent_seat_price_id,
                $this->billableAgentSeats($owner, $plan)
            );
            $this->syncSeatPrice(
                $subscription,
                $plan->stripe_company_seat_price_id,
                $this->billableCompanySeats($owner, $plan)
            );

            return true;
        } catch (\Throwable $exception) {
            Log::error('Plan seat billing sync failed', [
                'user_id' => $owner->id,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function syncForCompanyOwner(Company $company): bool
    {
        $owner = $company->user;

        if ($owner === null || ! $owner->hasRole('owner')) {
            return false;
        }

        return $this->syncForOwner($owner);
    }

    /**
     * @return array<int, array{key: string, label: string, used: int, included: int, billable: int, unit_price: float|null}>
     */
    public function getBillingSummary(User $owner): array
    {
        $plan = $this->planResourceLimit->resolvePlanForUser($owner);

        if ($plan === null) {
            return [];
        }

        $summary = [];

        if ($this->hasAgentSeatPrice($plan)) {
            $included = (int) ($plan->included_agent_seats ?? 0);
            $used = $this->planResourceLimit->countTotalAgentsForOwner($owner);

            $summary[] = [
                'key' => 'agents',
                'label' => __('Agent seats'),
                'used' => $used,
                'included' => $included,
                'billable' => max(0, $used - $included),
                'unit_price' => (float) ($plan->agent_seat_price ?? 0),
            ];
        }

        if ($this->hasCompanySeatPrice($plan)) {
            $included = (int) ($plan->included_companies ?? 0);
            $used = $this->planResourceLimit->countCompanies($owner);

            $summary[] = [
                'key' => 'companies',
                'label' => __('Organizations'),
                'used' => $used,
                'included' => $included,
                'billable' => max(0, $used - $included),
                'unit_price' => (float) ($plan->company_seat_price ?? 0),
            ];
        }

        return $summary;
    }

    public function applySeatPricesToSubscriptionBuilder($builder, User $owner, Plans $plan): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $agentQty = $this->billableAgentSeats($owner, $plan);
        if ($agentQty > 0 && $this->hasAgentSeatPrice($plan)) {
            $builder->price($plan->stripe_agent_seat_price_id, $agentQty);
        }

        $companyQty = $this->billableCompanySeats($owner, $plan);
        if ($companyQty > 0 && $this->hasCompanySeatPrice($plan)) {
            $builder->price($plan->stripe_company_seat_price_id, $companyQty);
        }
    }

    public function resolveSubscription(User $owner): ?Subscription
    {
        foreach (['default', 'main'] as $name) {
            if ($owner->subscribed($name)) {
                return $owner->subscription($name);
            }
        }

        return null;
    }

    private function syncSeatPrice(Subscription $subscription, ?string $priceId, int $quantity): void
    {
        if (! is_string($priceId) || strlen(trim($priceId)) <= 2) {
            return;
        }

        $subscription->refresh();
        $hasPrice = $subscription->items->contains('stripe_price', $priceId);

        if ($quantity <= 0) {
            if ($hasPrice) {
                $subscription->removePrice($priceId);
            }

            return;
        }

        if ($hasPrice) {
            $subscription->updateQuantity($quantity, $priceId);

            return;
        }

        $subscription->addPrice($priceId, $quantity);
    }
}
