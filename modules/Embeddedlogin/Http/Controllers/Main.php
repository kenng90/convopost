<?php

namespace Modules\Embeddedlogin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\Embeddedlogin\Services\EmbeddedSignupCompletionService;
use Modules\Embeddedlogin\Services\EmbeddedSignupSession;

class Main extends Controller
{
    public function start(Request $request, string $code, EmbeddedSignupCompletionService $completion)
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $session = EmbeddedSignupSession::fromRequest($request->all());
        $result = $completion->complete($user, $code, $session);

        if (($result['status'] ?? '') === 'success') {
            return response()->json($result, 200);
        }

        return response()->json($result, 200);
    }

    /**
     * @deprecated Use EmbeddedSignupCompletionService::resolveWabidFromDebugToken via unit tests.
     */
    public function resolveWabidFromDebugToken(?array $wabidResult): ?string
    {
        return app(EmbeddedSignupCompletionService::class)->resolveWabidFromDebugToken($wabidResult);
    }
}
