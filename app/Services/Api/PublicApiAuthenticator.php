<?php

namespace App\Services\Api;

use App\Models\Company;
use App\Models\Plans;
use App\Models\User;
use App\Scopes\SetCompanyIdInSession;
use App\Services\PlanEntitlementResolver;
use App\Services\PlanUsageLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class PublicApiAuthenticator
{
    public function __construct(
        private readonly PlanUsageLimit $planUsageLimit,
        private readonly PlanEntitlementResolver $entitlementResolver,
    ) {
    }

    public function extractToken(Request $request): ?string
    {
        $plain = $request->bearerToken() ?: $request->input('token');

        if (! is_string($plain) || $plain === '' || $plain === '_') {
            return null;
        }

        return $plain;
    }

    public function mergeTokenIntoRequest(Request $request): void
    {
        $plain = $this->extractToken($request);

        if ($plain && ! $request->filled('token')) {
            $request->merge(['token' => $plain]);
        }
    }

    /**
     * @return array{user: User, company: Company, plan: Plans}|JsonResponse
     */
    public function authenticate(Request $request, bool $requireApiAccess = true): array|JsonResponse
    {
        PublicApiResponse::requestId($request);
        $this->mergeTokenIntoRequest($request);

        $plain = $this->extractToken($request);

        if ($plain === null) {
            return PublicApiResponse::error('invalid_token', 'Invalid token', 401);
        }

        $token = PersonalAccessToken::findToken($plain);

        if (! $token) {
            return PublicApiResponse::error('invalid_token', 'Invalid token', 401);
        }

        $user = User::query()->find($token->tokenable_id);

        if (! $user) {
            return PublicApiResponse::error('invalid_token', 'Invalid token', 401);
        }

        Auth::setUser($user);

        $plan = $this->planUsageLimit->resolvePlanForUser($user);

        if (! $plan) {
            return PublicApiResponse::error('invalid_plan', 'Invalid plan', 403);
        }

        if ($requireApiAccess && ! $this->entitlementResolver->hasCapability($plan, 'api_access')) {
            return PublicApiResponse::error('plan_forbidden', 'API access is not included in your plan', 403);
        }

        $company = $this->resolveCompany($request, $user);

        if (! $company) {
            return PublicApiResponse::error('company_not_found', 'No accessible company.', 403);
        }

        $exceeded = $this->planUsageLimit->firstExceededLimit($company, $plan);

        if ($exceeded !== null) {
            return PublicApiResponse::error(
                'plan_limit_exceeded',
                $this->planUsageLimit->exceededMessage($exceeded),
                403
            );
        }

        session([
            'company_id' => $company->id,
            'company_currency' => $company->currency,
            'company_convertion' => $company->do_covertion,
        ]);

        (new SetCompanyIdInSession)->handle((object) ['user' => $user]);
        session(['company_id' => $company->id]);

        $request->attributes->set('public_api_user', $user);
        $request->attributes->set('public_api_company', $company);
        $request->attributes->set('public_api_plan', $plan);

        return [
            'user' => $user,
            'company' => $company,
            'plan' => $plan,
        ];
    }

    public function resolveCompany(Request $request, User $user): ?Company
    {
        $requestedId = $request->header('X-Company-Id') ?: $request->input('company_id');

        if ($requestedId) {
            $company = $user->accessibleCompanies()->firstWhere('id', (int) $requestedId);

            return $company instanceof Company ? $company : null;
        }

        return $user->currentCompany();
    }

    public function rateLimitBucket(Plans $plan): string
    {
        $capabilities = $this->entitlementResolver->getCapabilities($plan);

        if ($capabilities === null) {
            return 'agency';
        }

        if ($this->entitlementResolver->hasCapability($plan, 'api_access')) {
            return 'pro';
        }

        return 'default';
    }
}
