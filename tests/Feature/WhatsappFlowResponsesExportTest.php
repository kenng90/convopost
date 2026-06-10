<?php

namespace Tests\Feature;

use Tests\TestCase;

class WhatsappFlowResponsesExportTest extends TestCase
{
    public function test_export_route_is_registered(): void
    {
        $this->assertSame(
            url('/whatsapp-flows/responses/export'),
            route('whatsapp-flows.responses.export')
        );
    }

    public function test_export_requires_authentication(): void
    {
        $response = $this->get(route('whatsapp-flows.responses.export', ['flowId' => 1]));

        $response->assertRedirect(route('login'));
    }
}
