<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Modules\Whatsappcall\Http\Middleware\ValidateAiWorkerSecret;
use Tests\TestCase;

class ValidateAiWorkerSecretTest extends TestCase
{
    public function test_rejects_missing_secret(): void
    {
        config(['whatsappcall.ai_worker_secret' => 'expected']);

        $middleware = new ValidateAiWorkerSecret;
        $request = Request::create('/api/whatsappcall/worker/calls/1', 'GET');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_accepts_matching_global_secret(): void
    {
        config(['whatsappcall.ai_worker_secret' => 'expected']);

        $middleware = new ValidateAiWorkerSecret;
        $request = Request::create('/api/whatsappcall/worker/calls/1', 'GET');
        $request->headers->set('X-Worker-Secret', 'expected');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }
}
