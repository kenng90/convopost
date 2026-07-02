<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCampaignDispatchToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Campaign-Dispatch-Token')
            ?? $request->query('token')
            ?? $request->input('token');

        $expected = config('wpbox.campaign_dispatch_token');

        if (empty($expected)) {
            $expected = hash('sha256', config('app.key').':campaign-dispatch');
        }

        if (! is_string($token) || ! hash_equals($expected, $token)) {
            abort(403, 'Invalid campaign dispatch token');
        }

        return $next($request);
    }
}
