<?php

namespace Tests\Unit;

use App\Services\Catalog\CatalogFlowCallbackService;
use Tests\TestCase;

class CatalogFlowCallbackServiceTest extends TestCase
{
    public function test_it_encodes_and_decodes_flow_context_token(): void
    {
        $service = new CatalogFlowCallbackService;

        $token = $service->makeToken(12, 34, 'node-1', 99);
        $decoded = $service->decodeToken($token);

        $this->assertNotNull($decoded);
        $this->assertSame(12, $decoded['flow_id']);
        $this->assertSame(34, $decoded['contact_id']);
        $this->assertSame('node-1', $decoded['node_id']);
        $this->assertSame(99, $decoded['catalog_id']);
    }

    public function test_invalid_token_returns_null(): void
    {
        $service = new CatalogFlowCallbackService;

        $this->assertNull($service->decodeToken('not-a-valid-token'));
    }
}
