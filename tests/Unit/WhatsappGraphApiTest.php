<?php

namespace Tests\Unit;

use App\Support\WhatsappGraphApi;
use Tests\TestCase;

class WhatsappGraphApiTest extends TestCase
{
    public function test_it_builds_versioned_graph_api_urls(): void
    {
        config(['whatsapp-flows.graph_api_version' => 'v19.0']);

        $this->assertSame('https://graph.facebook.com/v19.0/123/messages', WhatsappGraphApi::url('123/messages'));
    }
}
