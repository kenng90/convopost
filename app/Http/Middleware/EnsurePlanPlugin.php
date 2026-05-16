<?php

namespace App\Http\Middleware;

use Akaunting\Module\Facade as Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanPlugin
{
    /**
     * Ensure the authenticated company's plan includes the given plugin alias.
     */
    public function handle(Request $request, Closure $next, string $plugin): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('admin') && ! $request->session()->has('impersonate')) {
            abort(403, __('This area is not available in the admin portal.'));
        }

        if (! Module::has($plugin) || (int) Module::get($plugin)->get('active') !== 1) {
            abort(404);
        }

        $company = $user->currentCompany();

        if (! $company || ! $company->hasPlanPlugin($plugin)) {
            return redirect()
                ->route('plans.current')
                ->withError(__('This feature is not included in your plan.'));
        }

        return $next($request);
    }
}
