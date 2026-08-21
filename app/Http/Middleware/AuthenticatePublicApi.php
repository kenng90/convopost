<?php

namespace App\Http\Middleware;

use App\Services\Api\PublicApiAuthenticator;
use App\Services\Api\PublicApiResponse;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePublicApi
{
    public function __construct(
        private readonly PublicApiAuthenticator $authenticator,
        private readonly RateLimiter $rateLimiter,
    ) {
    }

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $auth = $this->authenticator->authenticate($request, true);

        if ($auth instanceof JsonResponse) {
            return $auth->header('X-Request-Id', PublicApiResponse::requestId($request));
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
            $response = PublicApiResponse::error('rate_limited', 'Too many requests.', 429);
            $response->headers->set('Retry-After', (string) $retryAfter);

            return PublicApiResponse::withRateLimitHeaders(
                $response,
                $limit,
                0,
                now()->addSeconds($retryAfter)->getTimestamp()
            );
        }

        $this->rateLimiter->hit($key, 60);
        $remaining = $limit - $this->rateLimiter->attempts($key);
        $reset = now()->addSeconds($this->rateLimiter->availableIn($key) ?: 60)->getTimestamp();

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->headers->set('X-Request-Id', PublicApiResponse::requestId($request));

            return PublicApiResponse::withRateLimitHeaders($response, $limit, $remaining, $reset);
        }

        $response->headers->set('X-Request-Id', PublicApiResponse::requestId($request));
        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', (string) $reset);

        return $response;
    }
}
