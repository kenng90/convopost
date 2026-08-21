<?php

namespace Modules\Wpbox\Http\Middleware;

use App\Services\Api\PublicApiAuthenticator;
use App\Services\Api\PublicApiResponse;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckAPIPlan
{
    public function __construct(
        private readonly PublicApiAuthenticator $authenticator,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $auth = $this->authenticator->authenticate($request, true);

        if ($auth instanceof JsonResponse) {
            return $auth;
        }

        $plan = $auth['plan'];
        $company = $auth['company'];
        $bucket = $this->authenticator->rateLimitBucket($plan);
        $isWrite = ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        $limits = config('public-api.rate_limits.'.$bucket, config('public-api.rate_limits.default'));
        $limit = (int) ($isWrite ? $limits['write'] : $limits['read']);
        $key = 'public-api:'.$company->id.':'.($isWrite ? 'write' : 'read');

        if ($this->rateLimiter->tooManyAttempts($key, $limit)) {
            $retryAfter = $this->rateLimiter->availableIn($key);

            return PublicApiResponse::error('rate_limited', 'Too many requests.', 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        $this->rateLimiter->hit($key, 60);

        return $next($request);
    }
}
