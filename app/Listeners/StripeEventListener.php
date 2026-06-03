<?php

namespace App\Listeners;

use App\Models\Plans;
use App\Models\User;
use App\Services\PlanCreditAllocator;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookHandled;

class StripeEventListener
{
    public function __construct(private readonly PlanCreditAllocator $planCreditAllocator)
    {
    }

    /**
     * Create the event listener.
     */
    public function handle(WebhookHandled $event): void
    {
        if ($event->payload['type'] === 'checkout.session.completed') {
            //Get the user
            try {
                $user = User::where('stripe_id', $event->payload['data']['object']['customer'])->first();
                if ($user) {
                    //Get the plan
                    $plan = Plans::where('stripe_id', $event->payload['data']['object']['metadata']['plan_id'])->first();
                    if ($plan) {
                        $user->plan_id = $plan->id;
                        $user->plan_status = 'active';
                        $user->save();

                        if ($user->company) {
                            $this->planCreditAllocator->grantForCompany($user->company, $plan);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error('Error activating credits: '.$e->getMessage());
            }
        }
    }
}
