<?php

namespace App\Http\Middleware;

use App\Services\OrgAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrgRouteAccess
{
    public function __construct(private readonly OrgAuthorization $orgAuthorization)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if ($this->orgAuthorization->isPlatformAdmin($user) || $this->orgAuthorization->isOwnerAccount($user)) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($this->orgAuthorization->canAccessRoute($user, $routeName)) {
            return $next($request);
        }

        abort(403, __('You do not have access to this area.'));
    }
}
