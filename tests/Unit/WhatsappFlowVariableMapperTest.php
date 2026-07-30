<?php

namespace Tests\Unit;

use App\Services\WhatsappFlowVariableMapper;
use Tests\TestCase;

class WhatsappFlowVariableMapperTest extends TestCase
{
    public function test_it_resolves_custom_prefix(): void
    {
        $mapper = app(WhatsappFlowVariableMapper::class);

        $this->assertSame('intake', $mapper->resolvePrefix(['variablePrefix' => 'intake'], 'whatsapp_flow-1'));
        $this->assertSame('whatsapp_flow_1', $mapper->resolvePrefix([], 'whatsapp_flow-1'));
    }

    public function test_it_flattens_nested_response_values(): void
    {
        $mapper = app(WhatsappFlowVariableMapper::class);

        $flat = $mapper->flattenNestedValues([
            'name' => 'Jane',
            'address' => [
                'city' => 'Nairobi',
                'zip' => '00100',
            ],
            'tags' => ['a', 'b'],
        ]);

        $this->assertSame('Jane', $flat['name']);
        $this->assertSame('Nairobi', $flat['address.city']);
        $this->assertSame('00100', $flat['address.zip']);
        $this->assertSame(['a', 'b'], $flat['tags']);
    }
}
