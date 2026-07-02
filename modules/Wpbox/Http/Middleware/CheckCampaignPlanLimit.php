<?php

namespace Modules\Wpbox\Http\Middleware;

use App\Services\PlanUsageLimit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCampaignPlanLimit
{
    public function __construct(
        private readonly PlanUsageLimit $planUsageLimit,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        if (Auth::user()->hasRole('admin')) {
            return $next($request);
        }

        $plan = $this->planUsageLimit->resolvePlanForUser(Auth::user());

        if (! $plan) {
            return redirect(route('plans.current'))->withError(__('There is no plan assigned to your account. Please contact the administrator.'));
        }

        $company = Auth::user()->currentCompany();
        $allowed = $this->planUsageLimit->getAllowedLimits($plan);
        $usage = $this->planUsageLimit->getUsageForCompany($company, $plan);

        if (! $this->planUsageLimit->isUnlimited($allowed['campaigns']) && $usage['campaigns'] >= $allowed['campaigns']) {
            return redirect(route('plans.current'))->withError($this->planUsageLimit->exceededMessage('campaigns'));
        }

        return $next($request);
    }
}
