<?php

namespace App\Listeners;

use App\Models\Plans;
use App\Services\DefaultPlanService;
use App\Services\PlanCreditAllocator;
use App\Services\PlanSeatBillingService;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookHandled;

class StripeEventListener
{
    public function __construct(
        private readonly PlanCreditAllocator $planCreditAllocator,
        private readonly PlanSeatBillingService $planSeatBillingService,
        private readonly DefaultPlanService $defaultPlanService,
    ) {
    }

    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;
        $object = $event->payload['data']['object'] ?? [];

        try {
            match ($type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($object),
                'customer.subscription.deleted',
                'customer.subscription.updated' => $this->defaultPlanService->handleStripeSubscriptionChange($object),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function handleCheckoutSessionCompleted(array $session): void
    {
        $customerId = $session['customer'] ?? null;
        $user = $this->defaultPlanService->userFromStripeCustomer(is_string($customerId) ? $customerId : null);

        if ($user === null) {
            return;
        }

        $planId = $session['metadata']['plan_id'] ?? null;
        $plan = $planId !== null ? Plans::find($planId) : null;

        if ($plan === null) {
            return;
        }

        $user->plan_id = $plan->id;
        $user->plan_status = 'active';
        $user->save();

        $this->planCreditAllocator->replacePlanCreditsForUser($user, $plan);
        $this->planSeatBillingService->syncForOwner($user);
    }
}
