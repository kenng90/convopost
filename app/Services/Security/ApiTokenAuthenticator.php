<?php

namespace App\Services\Security;

use App\Models\User;
use App\Scopes\SetCompanyIdInSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenAuthenticator
{
    /**
     * Authenticate an API request from a Sanctum token (preferred) or an
     * existing session. A provided token is always validated; a forged token
     * cannot fall back to a browser session.
     */
    public function authenticate(Request $request): User|JsonResponse
    {
        $plain = $request->bearerToken() ?: $request->input('token');

        if (is_string($plain) && $plain !== '' && $plain !== '_') {
            $token = PersonalAccessToken::findToken($plain);
            if (! $token) {
                return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
            }

            $user = User::query()->find($token->tokenable_id);
            if (! $user) {
                return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
            }

            Auth::setUser($user);
            $this->bindCompany($user);

            return $user;
        }

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            $this->bindCompany($user);

            return $user;
        }

        return response()->json(['status' => 'error', 'message' => 'Invalid token'], 401);
    }

    public function bindCompany(User $user): void
    {
        (new SetCompanyIdInSession)->handle((object) ['user' => $user]);
    }
}
