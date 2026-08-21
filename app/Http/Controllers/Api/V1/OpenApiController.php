<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OpenApiController extends Controller
{
    public static function documentationUrl(): string
    {
        $configured = (string) config('wpbox.api_docs', '/api/v1/docs');

        if ($configured === '') {
            return url('/api/v1/docs');
        }

        if (str_starts_with($configured, 'http://') || str_starts_with($configured, 'https://')) {
            return $configured;
        }

        return url($configured);
    }

    public function yaml(): Response
    {
        $path = resource_path('openapi/v1.yaml');

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }

    public function json(): JsonResponse
    {
        $path = resource_path('openapi/v1.json');

        abort_unless(is_file($path), 404);

        $spec = json_decode((string) file_get_contents($path), true);

        return response()->json($spec);
    }

    public function docs()
    {
        return view('api.v1-docs', [
            'specUrl' => url('/api/v1/openapi.json'),
        ]);
    }
}
