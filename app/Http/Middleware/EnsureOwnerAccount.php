<?php

namespace App\Http\Middleware;

use App\Services\OrgAuthorization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerAccount
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

        if ($this->orgAuthorization->isPlatformAdmin($user)) {
            return $next($request);
        }

        if (! $this->orgAuthorization->isOwnerAccount($user)) {
            abort(403, __('Unauthorized action.'));
        }

        return $next($request);
    }
}
