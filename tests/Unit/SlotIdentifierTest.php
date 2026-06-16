<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Modules\Reminders\Support\SlotIdentifier;
use Tests\TestCase;

class SlotIdentifierTest extends TestCase
{
    public function test_it_encodes_and_decodes_slot_ids(): void
    {
        $start = Carbon::parse('2026-06-15 09:00:00', 'UTC');
        $slotId = SlotIdentifier::encode(42, $start, 30);

        $decoded = SlotIdentifier::decode($slotId);

        $this->assertNotNull($decoded);
        $this->assertSame(42, $decoded['appointment_staff_id']);
        $this->assertSame(30, $decoded['duration_minutes']);
        $this->assertSame('2026-06-15 09:00:00', $decoded['start']->format('Y-m-d H:i:s'));
    }
}
