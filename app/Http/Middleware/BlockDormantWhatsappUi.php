<?php

namespace App\Http\Middleware;

use App\Support\Offering;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class BlockDormantWhatsappUi
{
    /**
     * Block authenticated WhatsApp CRM UI while offering mode is social_commerce.
     * Public webhooks are not named in offering.whatsapp_dormant_routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Offering::whatsappDormant()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $route = $request->route();
        $routeName = $route?->getName();

        if (! Offering::isDormantRoute($routeName)) {
            return $next($request);
        }

        if ($user->hasRole('admin') && ! $request->session()->has('impersonate')) {
            return $next($request);
        }

        $home = Offering::socialHomeRoute();

        if (Route::has($home)) {
            return redirect()
                ->route($home)
                ->withError(__('WhatsApp messaging features are temporarily unavailable on this offering.'));
        }

        return redirect()
            ->route('dashboard')
            ->withError(__('WhatsApp messaging features are temporarily unavailable on this offering.'));
    }
}
