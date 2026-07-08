<?php

namespace Tests\Unit;

use Modules\Flowmaker\Models\Nodes\WhatsAppCatalog;
use ReflectionMethod;
use Tests\TestCase;

class WhatsAppCatalogProductIdTest extends TestCase
{
    public function test_resolve_product_id_from_extra_handles_node_ids_with_underscores(): void
    {
        $node = new WhatsAppCatalog([
            'id' => 'whatsapp_catalog-1',
            'type' => 'whatsapp_catalog',
            'data' => [],
        ], []);
        $node->flow_id = 18;

        $method = new ReflectionMethod(WhatsAppCatalog::class, 'resolveProductIdFromExtra');
        $method->setAccessible(true);

        $extra = 'catalog_LIST_001_idwhatsapp_catalog-1_flow18';

        $this->assertSame('LIST_001', $method->invoke($node, $extra));
    }

    public function test_resolve_product_id_from_extra_handles_product_ids_with_underscores(): void
    {
        $node = new WhatsAppCatalog([
            'id' => 'whatsapp_catalog-1',
            'type' => 'whatsapp_catalog',
            'data' => [],
        ], []);
        $node->flow_id = 18;

        $method = new ReflectionMethod(WhatsAppCatalog::class, 'resolveProductIdFromExtra');
        $method->setAccessible(true);

        $extra = 'catalog_iphone_15_pro_idwhatsapp_catalog-1_flow18';

        $this->assertSame('iphone_15_pro', $method->invoke($node, $extra));
    }
}
