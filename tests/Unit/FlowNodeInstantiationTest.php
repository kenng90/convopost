<?php

namespace Tests\Unit;

use Modules\Flowmaker\Models\Nodes\BookAppointment;
use Modules\Flowmaker\Models\Nodes\CatalogSearch;
use Modules\Flowmaker\Models\Nodes\ListMessage;
use Modules\Flowmaker\Models\Nodes\Node;
use Modules\Flowmaker\Models\Nodes\RequestPayment;
use PHPUnit\Framework\TestCase;

class FlowNodeInstantiationTest extends TestCase
{
    public function test_list_message_node_can_be_constructed_and_serialized(): void
    {
        $nodeData = [
            'id' => 'list-1',
            'type' => 'list_message',
            'data' => [
                'settings' => [
                    'header' => 'Choose',
                    'body' => 'Pick one',
                    'sections' => [],
                ],
            ],
        ];

        $node = new ListMessage($nodeData, []);
        $node->flow_id = 7;

        $this->assertSame('list-1', $node->id);
        $this->assertSame('list_message', $node->type);
        $this->assertSame(7, $node->flow_id);

        $restored = unserialize(serialize($node));

        $this->assertInstanceOf(ListMessage::class, $restored);
        $this->assertSame('list-1', $restored->id);
        $this->assertSame(7, $restored->flow_id);
    }

    public function test_generic_node_does_not_require_eloquent_bootstrap(): void
    {
        $node = new Node([
            'id' => 'fallback-1',
            'type' => 'unknown',
            'data' => [],
        ], []);

        $this->assertSame('fallback-1', $node->id);
        $this->assertFalse($node->isStartNode);
    }

    public function test_book_appointment_node_can_be_constructed_and_serialized(): void
    {
        $nodeData = [
            'id' => 'book-1',
            'type' => 'book_appointment',
            'data' => [
                'settings' => [
                    'source_name' => 'Consultation',
                    'duration_minutes' => 30,
                ],
            ],
        ];

        $node = new BookAppointment($nodeData, []);
        $node->flow_id = 12;

        $this->assertSame('book-1', $node->id);
        $this->assertSame('book_appointment', $node->type);
        $this->assertSame(12, $node->flow_id);

        $restored = unserialize(serialize($node));

        $this->assertInstanceOf(BookAppointment::class, $restored);
        $this->assertSame('book-1', $restored->id);
    }

    public function test_request_payment_node_can_be_constructed_and_serialized(): void
    {
        $nodeData = [
            'id' => 'pay-1',
            'type' => 'request_payment',
            'data' => [
                'settings' => [
                    'payment' => [
                        'amount' => '{{catalog_order_total_amount}}',
                        'provider' => 'auto',
                        'accountReference' => 'ORDER',
                        'description' => 'Order payment',
                        'responseVar' => 'payment_result',
                    ],
                ],
            ],
        ];

        $node = new RequestPayment($nodeData, []);
        $node->flow_id = 21;

        $this->assertSame('pay-1', $node->id);
        $this->assertSame('request_payment', $node->type);
        $this->assertSame(21, $node->flow_id);

        $restored = unserialize(serialize($node));

        $this->assertInstanceOf(RequestPayment::class, $restored);
        $this->assertSame('pay-1', $restored->id);
        $this->assertSame('auto', $restored->getDataAsArray()['settings']['payment']['provider']);
    }

    public function test_catalog_search_node_can_be_constructed_and_serialized(): void
    {
        $nodeData = [
            'id' => 'search-1',
            'type' => 'catalog_search',
            'data' => [
                'settings' => [
                    'searchPrompt' => 'What are you looking for?',
                    'maxResults' => 5,
                    'header' => 'Search results',
                ],
            ],
        ];

        $node = new CatalogSearch($nodeData, []);
        $node->flow_id = 33;

        $this->assertSame('search-1', $node->id);
        $this->assertSame('catalog_search', $node->type);
        $this->assertSame(33, $node->flow_id);

        $restored = unserialize(serialize($node));

        $this->assertInstanceOf(CatalogSearch::class, $restored);
        $this->assertSame('search-1', $restored->id);
        $this->assertSame(5, $restored->getDataAsArray()['settings']['maxResults']);
    }
}
