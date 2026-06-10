<?php

namespace Modules\Wpbox\Http\Middleware;

use App\Services\PlanUsageLimit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPlan
{
    public function __construct(
        private readonly PlanUsageLimit $planUsageLimit,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
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
        $exceeded = $this->planUsageLimit->firstExceededLimit($company, $plan);

        if ($exceeded !== null) {
            return redirect(route('plans.current'))->withError($this->planUsageLimit->exceededMessage($exceeded));
        }

        return $next($request);
    }
}
