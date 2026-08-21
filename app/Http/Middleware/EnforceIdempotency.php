<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use App\Models\Company;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdempotency
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            return $next($request);
        }

        $company = $request->attributes->get('public_api_company');

        if (! $company instanceof Company) {
            return $next($request);
        }

        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        $existing = ApiIdempotencyKey::query()
            ->where('company_id', $company->id)
            ->where('idempotency_key', $key)
            ->where('created_at', '>=', now()->subHours((int) config('public-api.idempotency_ttl_hours', 24)))
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $hash)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Idempotency key was reused with a different request.',
                    'error' => [
                        'code' => 'idempotency_conflict',
                        'message' => 'Idempotency key was reused with a different request.',
                        'details' => (object) [],
                        'request_id' => $request->attributes->get('public_api_request_id'),
                    ],
                ], 409);
            }

            return response()->json(json_decode($existing->response_body, true), $existing->response_code)
                ->header('Idempotent-Replay', 'true');
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
            ApiIdempotencyKey::query()->create([
                'company_id' => $company->id,
                'user_id' => optional($request->attributes->get('public_api_user'))->id,
                'idempotency_key' => $key,
                'request_hash' => $hash,
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);
        }

        return $response;
    }
}
