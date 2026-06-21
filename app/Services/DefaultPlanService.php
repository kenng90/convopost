<?php

namespace App\Services;

use App\Models\Plans;
use App\Models\User;

class DefaultPlanService
{
    public function __construct(
        private readonly PlanCreditAllocator $planCreditAllocator,
    ) {
    }

    public function resolveDefaultPlan(): ?Plans
    {
        $configuredPlanId = (int) config('settings.free_pricing_id');

        $plan = Plans::withTrashed()->find($configuredPlanId);

        if ($plan !== null) {
            return $plan;
        }

        return Plans::query()->where('name', 'Starter')->first();
    }

    public function defaultPlanId(): int
    {
        return $this->resolveDefaultPlan()?->id ?? (int) config('settings.free_pricing_id');
    }

    public function assignToUser(User $user, bool $refreshCredits = true): void
    {
        $plan = $this->resolveDefaultPlan();

        if ($plan === null) {
            return;
        }

        $user->plan_id = $plan->id;
        $user->plan_status = null;
        $user->save();

        if ($refreshCredits) {
            $this->planCreditAllocator->replacePlanCreditsForUser($user, $plan);
        }
    }

    public function userFromStripeCustomer(?string $stripeCustomerId): ?User
    {
        if ($stripeCustomerId === null || $stripeCustomerId === '') {
            return null;
        }

        return User::query()->where('stripe_id', $stripeCustomerId)->first();
    }

    public function shouldDowngradeFromStripeSubscription(array $subscription): bool
    {
        $status = $subscription['status'] ?? null;

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            return true;
        }

        return ($subscription['pause_collection'] ?? null) !== null;
    }

    public function isActiveStripeSubscription(array $subscription): bool
    {
        if ($this->shouldDowngradeFromStripeSubscription($subscription)) {
            return false;
        }

        $status = $subscription['status'] ?? null;

        return in_array($status, ['active', 'trialing'], true);
    }

    public function restorePaidPlanFromStripeSubscription(User $user, array $subscription): bool
    {
        $stripePriceId = $subscription['items']['data'][0]['price']['id'] ?? null;

        if ($stripePriceId === null) {
            return false;
        }

        $plan = Plans::query()->where('stripe_id', $stripePriceId)->first();

        if ($plan === null) {
            return false;
        }

        $user->plan_id = $plan->id;
        $user->plan_status = 'active';
        $user->save();

        $this->planCreditAllocator->replacePlanCreditsForUser($user, $plan);

        return true;
    }

    public function handleStripeSubscriptionChange(array $subscription): void
    {
        $customerId = $subscription['customer'] ?? null;
        $user = $this->userFromStripeCustomer(is_string($customerId) ? $customerId : null);

        if ($user === null) {
            return;
        }

        if ($this->shouldDowngradeFromStripeSubscription($subscription)) {
            $this->assignToUser($user);

            return;
        }

        if ($this->isActiveStripeSubscription($subscription) && $user->plan_id === $this->defaultPlanId()) {
            $this->restorePaidPlanFromStripeSubscription($user, $subscription);
        }
    }
}
