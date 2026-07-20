<?php

namespace Tests\Unit;

use App\Services\WhatsApp\InteractiveListLimits;
use Tests\TestCase;

class InteractiveListLimitsTest extends TestCase
{
    public function test_footer_is_truncated_to_whatsapp_limit(): void
    {
        $footer = 'Paid services will request the configured deposit before confirmation.';

        $this->assertSame(70, mb_strlen($footer));

        $truncated = InteractiveListLimits::truncate($footer, InteractiveListLimits::FOOTER);

        $this->assertSame(InteractiveListLimits::FOOTER, mb_strlen($truncated));
        $this->assertSame(mb_substr($footer, 0, 60), $truncated);
    }

    public function test_constrain_list_fields_enforces_all_limits(): void
    {
        $constrained = InteractiveListLimits::constrainListFields(
            str_repeat('H', 80),
            str_repeat('B', 1100),
            str_repeat('F', 70),
            str_repeat('Btn', 10),
            str_repeat('S', 40),
            [[
                'id' => str_repeat('i', 220),
                'title' => str_repeat('T', 40),
                'description' => str_repeat('D', 90),
            ]]
        );

        $this->assertSame(InteractiveListLimits::HEADER, mb_strlen($constrained['header']));
        $this->assertSame(InteractiveListLimits::BODY, mb_strlen($constrained['body']));
        $this->assertSame(InteractiveListLimits::FOOTER, mb_strlen($constrained['footer']));
        $this->assertSame(InteractiveListLimits::BUTTON, mb_strlen($constrained['button']));
        $this->assertSame(InteractiveListLimits::SECTION_TITLE, mb_strlen($constrained['section_title']));
        $this->assertSame(InteractiveListLimits::ROW_ID, mb_strlen($constrained['rows'][0]['id']));
        $this->assertSame(InteractiveListLimits::ROW_TITLE, mb_strlen($constrained['rows'][0]['title']));
        $this->assertSame(InteractiveListLimits::ROW_DESCRIPTION, mb_strlen($constrained['rows'][0]['description']));
    }

    public function test_spa_template_book_appointment_footer_fits_whatsapp_limit(): void
    {
        $footer = config('flow-templates.spa_wellness_booking.flow_data.nodes')[1]['data']['settings']['footer']
            ?? null;

        // Find book_appointment node footer regardless of node order.
        foreach (config('flow-templates.spa_wellness_booking.flow_data.nodes') as $node) {
            if (($node['type'] ?? '') === 'book_appointment') {
                $footer = $node['data']['settings']['footer'] ?? '';
                break;
            }
        }

        $this->assertNotEmpty($footer);
        $this->assertLessThanOrEqual(InteractiveListLimits::FOOTER, mb_strlen($footer));
    }
}
