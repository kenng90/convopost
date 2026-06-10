<?php

namespace Modules\Wpbox\Http\Middleware;

use App\Models\User;
use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CheckAPIPlan
{
    public function __construct(
        private readonly PlanUsageLimit $planUsageLimit,
        private readonly PlanEntitlementResolver $entitlementResolver,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $token = PersonalAccessToken::findToken($request->token);

        if (! $token) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token']);
        }

        $user = User::findOrFail($token->tokenable_id);
        $plan = $this->planUsageLimit->resolvePlanForUser($user);

        if (! $plan) {
            return response()->json(['status' => 'error', 'message' => 'Invalid plan']);
        }

        if (! $this->entitlementResolver->hasCapability($plan, 'api_access')) {
            return response()->json(['status' => 'error', 'message' => 'API access is not included in your plan']);
        }

        $company = $user->currentCompany();
        $exceeded = $this->planUsageLimit->firstExceededLimit($company, $plan);

        if ($exceeded !== null) {
            return response()->json([
                'status' => 'error',
                'message' => $this->planUsageLimit->exceededMessage($exceeded),
            ]);
        }

        return $next($request);
    }
}
