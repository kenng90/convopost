<?php

namespace App\Services\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicApiResponse
{
    public static function requestId(?Request $request = null): string
    {
        $request ??= request();

        $existing = $request->headers->get('X-Request-Id')
            ?: $request->attributes->get('public_api_request_id');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = 'req_'.str_replace('-', '', (string) \Illuminate\Support\Str::uuid());
        $request->attributes->set('public_api_request_id', $id);

        return $id;
    }

    public static function error(string $code, string $message, int $status = 400, array $details = [], array $legacy = []): JsonResponse
    {
        $payload = array_merge([
            'status' => 'error',
            'message' => $message,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
                'request_id' => self::requestId(),
            ],
        ], $legacy);

        return response()->json($payload, $status)->header('X-Request-Id', self::requestId());
    }

    public static function success(array $data, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'status' => 'success',
            'data' => $data,
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status)->header('X-Request-Id', self::requestId());
    }

    public static function withRateLimitHeaders(JsonResponse $response, int $limit, int $remaining, int $resetEpoch): JsonResponse
    {
        return $response
            ->header('X-RateLimit-Limit', (string) $limit)
            ->header('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->header('X-RateLimit-Reset', (string) $resetEpoch);
    }
}
