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
            if ($user->isOrganizationManager()) {
                abort(403, __('This feature is not included in your plan or access level.'));
            }

            return redirect()
                ->route('plans.current')
                ->withError(__('This feature is not included in your plan.'));
        }

        if ($user->isOrganizationManager() && ! app(\App\Services\OrgAuthorization::class)->canAccessModule($user, $plugin)) {
            abort(403, __('You do not have access to this module.'));
        }

        return $next($request);
    }
}
