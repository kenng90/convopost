<?php

namespace Tests\Unit;

use App\Models\WhatsappFlow;
use App\Services\Flowmaker\WhatsappFormFieldMapper;
use Tests\TestCase;

class WhatsappFormFieldMapperSchemaTest extends TestCase
{
    public function test_it_suggests_booking_field_map_from_schema(): void
    {
        $form = new WhatsappFlow([
            'flow_json' => [
                'screens' => [[
                    'fields' => [
                        ['id' => 1, 'type' => 'select', 'label' => 'Choose service', 'name' => 'service'],
                        ['id' => 2, 'type' => 'date', 'label' => 'Preferred date', 'name' => 'preferred_date'],
                        ['id' => 3, 'type' => 'select', 'label' => 'Time slot', 'name' => 'slot'],
                    ],
                ]],
            ],
        ]);
        $form->id = 1;

        $map = app(WhatsappFormFieldMapper::class)->suggestBookingFieldMap($form);

        $this->assertSame('service', $map['serviceField']);
        $this->assertSame('preferred_date', $map['dateField']);
        $this->assertSame('slot', $map['slotField']);
    }

    public function test_it_suggests_event_field_map_from_schema(): void
    {
        $form = new WhatsappFlow([
            'flow_json' => [
                'screens' => [[
                    'fields' => [
                        ['id' => 1, 'type' => 'select', 'label' => 'Event session', 'name' => 'occurrence_id'],
                        ['id' => 2, 'type' => 'text', 'label' => 'Party size', 'name' => 'party_size'],
                    ],
                ]],
            ],
        ]);
        $form->id = 1;

        $map = app(WhatsappFormFieldMapper::class)->suggestEventFieldMap($form);

        $this->assertSame('occurrence_id', $map['occurrenceField']);
        $this->assertSame('party_size', $map['partySizeField']);
    }
}
