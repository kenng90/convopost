<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Add conservative security headers that do not break Livewire, embeds, or WhatsApp calling.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-DNS-Prefetch-Control', 'off');
        $response->headers->set('Permissions-Policy', 'geolocation=(), browsing-topics=()');

        if (! $response->headers->has('X-Frame-Options') && ! $this->isEmbeddablePath($request)) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if ($request->secure() && app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function isEmbeddablePath(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        return str_starts_with($path, 'embed')
            || str_starts_with($path, 'shop/')
            || str_starts_with($path, 'catalog/');
    }
}
