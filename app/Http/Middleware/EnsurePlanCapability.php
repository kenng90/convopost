<?php

namespace App\Http\Middleware;

use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanCapability
{
    public function __construct(
        private readonly PlanUsageLimit $planUsageLimit,
        private readonly PlanEntitlementResolver $entitlementResolver,
    ) {
    }

    /**
     * Ensure the authenticated company's plan includes the given capability flag.
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('admin') && ! $request->session()->has('impersonate')) {
            abort(403, __('This area is not available in the admin portal.'));
        }

        $plan = $this->planUsageLimit->resolvePlanForUser($user);

        if (! $plan) {
            return redirect()
                ->route('plans.current')
                ->withError(__('There is no plan assigned to your account. Please contact the administrator.'));
        }

        if (! $this->entitlementResolver->hasCapability($plan, $capability)) {
            return redirect()
                ->route('plans.current')
                ->withError(__('This feature is not included in your plan.'));
        }

        return $next($request);
    }
}
