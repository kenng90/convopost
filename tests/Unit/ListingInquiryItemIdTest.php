<?php

namespace Tests\Unit;

use Modules\Flowmaker\Models\Nodes\ListingInquiry;
use ReflectionMethod;
use Tests\TestCase;

class ListingInquiryItemIdTest extends TestCase
{
    public function test_resolve_item_id_from_interactive_list_extra(): void
    {
        $node = new ListingInquiry([
            'id' => 'listing_inquiry-1',
            'type' => 'listing_inquiry',
            'data' => [],
        ], []);
        $node->flow_id = 22;

        $method = new ReflectionMethod(ListingInquiry::class, 'resolveItemIdFromExtra');
        $method->setAccessible(true);

        $extra = 'listing_ROOM_001_idlisting_inquiry-1_flow22';

        $this->assertSame('ROOM_001', $method->invoke($node, $extra));
    }

    public function test_resolve_item_id_from_web_callback_extra(): void
    {
        $node = new ListingInquiry([
            'id' => 'listing_inquiry-1',
            'type' => 'listing_inquiry',
            'data' => [],
        ], []);
        $node->flow_id = 22;

        $method = new ReflectionMethod(ListingInquiry::class, 'resolveItemIdFromExtra');
        $method->setAccessible(true);

        $this->assertSame('ROOM_001', $method->invoke($node, 'listing:ROOM_001'));
    }

    public function test_resolve_item_id_handles_item_ids_with_underscores(): void
    {
        $node = new ListingInquiry([
            'id' => 'listing_inquiry-1',
            'type' => 'listing_inquiry',
            'data' => [],
        ], []);
        $node->flow_id = 22;

        $method = new ReflectionMethod(ListingInquiry::class, 'resolveItemIdFromExtra');
        $method->setAccessible(true);

        $extra = 'listing_deluxe_suite_a_idlisting_inquiry-1_flow22';

        $this->assertSame('deluxe_suite_a', $method->invoke($node, $extra));
    }
}
