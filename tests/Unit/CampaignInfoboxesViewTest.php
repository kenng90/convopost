<?php

namespace Tests\Unit;

use Tests\TestCase;

class CampaignInfoboxesViewTest extends TestCase
{
    public function test_infoboxes_renders_when_total_contacts_is_zero(): void
    {
        $item = (object) [
            'template' => (object) ['name' => 'Test Template'],
            'send_to' => 0,
            'delivered_to' => 0,
            'read_by' => 0,
            'is_bot' => false,
            'is_api' => false,
            'timestamp_for_delivery' => null,
            'created_at' => now(),
        ];

        $html = view('wpbox::campaigns.infoboxes', [
            'item' => $item,
            'total_contacts' => 0,
        ])->render();

        $this->assertStringContainsString('0% of your contacts', strip_tags($html));
    }
}
