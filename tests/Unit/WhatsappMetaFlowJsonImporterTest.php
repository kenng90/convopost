<?php

namespace Tests\Unit;

use App\Services\WhatsappMetaFlowJsonImporter;
use Tests\TestCase;

class WhatsappMetaFlowJsonImporterTest extends TestCase
{
    public function test_it_converts_meta_json_to_local_screens(): void
    {
        $importer = app(WhatsappMetaFlowJsonImporter::class);

        $local = $importer->toLocalFormat([
            'version' => '7.0',
            'screens' => [[
                'id' => 'WELCOME',
                'title' => 'Welcome',
                'layout' => [
                    'children' => [[
                        'type' => 'Form',
                        'children' => [[
                            'type' => 'TextInput',
                            'name' => 'full_name',
                            'label' => 'Full Name',
                            'required' => true,
                        ]],
                    ]],
                ],
            ]],
        ]);

        $this->assertTrue($local['imported_from_meta'] ?? false);
        $this->assertCount(1, $local['screens']);
        $this->assertSame('WELCOME', $local['screens'][0]['id']);
        $this->assertSame('text', $local['screens'][0]['fields'][0]['type']);
        $this->assertSame('full_name', $local['screens'][0]['fields'][0]['meta_name']);
    }

    public function test_it_preserves_string_schema_fields_on_import(): void
    {
        $importer = app(WhatsappMetaFlowJsonImporter::class);

        $local = $importer->toLocalFormat([
            'screens' => [[
                'id' => 'LOAN',
                'title' => 'Loan calculator',
                'data' => [
                    'emi' => ['type' => 'string', '__example__' => '1200'],
                    'rate' => ['type' => 'string', '__example__' => '12%'],
                    'fee' => ['type' => 'string', '__example__' => '50'],
                    'selected_amount' => ['type' => 'string', '__example__' => '10000'],
                    'selected_tenure' => ['type' => 'string', '__example__' => '12'],
                    'tenure_options' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'title' => ['type' => 'string'],
                            ],
                        ],
                        '__example__' => [['id' => '12', 'title' => '12 months']],
                    ],
                ],
                'layout' => ['children' => []],
            ]],
        ]);

        $dynamic = collect($local['screens'][0]['dynamic_data'])->keyBy('key');

        $this->assertSame('string', $dynamic['emi']['type']);
        $this->assertSame('1200', $dynamic['emi']['example']);
        $this->assertSame('option_list', $dynamic['tenure_options']['type']);
    }
}
