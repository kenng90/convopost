<?php

namespace Tests\Unit;

use App\Services\WhatsappMetaFlowJsonImporter;
use Tests\TestCase;

class WhatsappMetaFlowJsonImporterTest extends TestCase
{
    private WhatsappMetaFlowJsonImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(WhatsappMetaFlowJsonImporter::class);
    }

    public function test_it_converts_meta_json_to_local_screens(): void
    {
        $local = $this->importer->toLocalFormat([
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
        $local = $this->importer->toLocalFormat([
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

    public function test_it_imports_display_components_and_footer(): void
    {
        $local = $this->importer->toLocalFormat([
            'screens' => [[
                'id' => 'INTRO',
                'title' => 'Introduction',
                'layout' => [
                    'children' => [[
                        'type' => 'Form',
                        'children' => [
                            [
                                'type' => 'TextHeading',
                                'text' => 'Welcome to our service',
                            ],
                            [
                                'type' => 'TextBody',
                                'text' => 'Please fill in the form below.',
                            ],
                            [
                                'type' => 'Footer',
                                'label' => 'Get Started',
                                'on-click-action' => [
                                    'name' => 'navigate',
                                    'next' => ['name' => 'FORM', 'type' => 'screen'],
                                ],
                            ],
                        ],
                    ]],
                ],
            ]],
        ]);

        $types = array_column($local['screens'][0]['fields'], 'type');

        $this->assertSame(['heading', 'body', 'footer'], $types);
        $this->assertSame('Welcome to our service', $local['screens'][0]['fields'][0]['label']);
        $this->assertSame('Get Started', $local['screens'][0]['fields'][2]['label']);
        $this->assertSame('navigate', $local['screens'][0]['fields'][2]['on_click_action']);
        $this->assertSame('FORM', $local['screens'][0]['fields'][2]['navigate_next']);
    }

    public function test_it_maps_dropdown_to_select_with_dynamic_data_source(): void
    {
        $local = $this->importer->toLocalFormat([
            'screens' => [[
                'id' => 'OPTIONS',
                'title' => 'Options',
                'data' => [
                    'tenure_options' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'title' => ['type' => 'string'],
                            ],
                        ],
                        '__example__' => [
                            ['id' => '12', 'title' => '12 months'],
                            ['id' => '24', 'title' => '24 months'],
                        ],
                    ],
                ],
                'layout' => [
                    'children' => [[
                        'type' => 'Form',
                        'children' => [[
                            'type' => 'Dropdown',
                            'name' => 'tenure',
                            'label' => 'Select tenure',
                            'data-source' => '${data.tenure_options}',
                        ]],
                    ]],
                ],
            ]],
        ]);

        $field = $local['screens'][0]['fields'][0];

        $this->assertSame('select', $field['type']);
        $this->assertTrue($field['dynamic_data_source']);
        $this->assertSame('tenure_options', $field['data_source_key']);
        $this->assertCount(2, $field['options']);
        $this->assertSame('12 months', $field['options'][0]['label']);
        $this->assertSame('12', $field['options'][0]['value']);
    }

    public function test_it_assigns_unique_sequential_field_ids_across_screens(): void
    {
        $local = $this->importer->toLocalFormat([
            'screens' => [
                [
                    'id' => 'A',
                    'title' => 'Screen A',
                    'layout' => [
                        'children' => [[
                            'type' => 'Form',
                            'children' => [[
                                'type' => 'TextInput',
                                'name' => 'field_a',
                                'label' => 'Field A',
                            ]],
                        ]],
                    ],
                ],
                [
                    'id' => 'B',
                    'title' => 'Screen B',
                    'layout' => [
                        'children' => [[
                            'type' => 'Form',
                            'children' => [[
                                'type' => 'TextInput',
                                'name' => 'field_b',
                                'label' => 'Field B',
                            ]],
                        ]],
                    ],
                ],
            ],
        ]);

        $ids = [
            $local['screens'][0]['fields'][0]['id'],
            $local['screens'][1]['fields'][0]['id'],
        ];

        $this->assertSame([1, 2], $ids);
    }

    public function test_it_imports_if_condition_with_nested_children(): void
    {
        $local = $this->importer->toLocalFormat([
            'screens' => [[
                'id' => 'CONDITIONAL',
                'title' => 'Conditional',
                'layout' => [
                    'children' => [[
                        'type' => 'If',
                        'condition' => '${form.optin} == true',
                        'then' => [[
                            'type' => 'TextBody',
                            'text' => 'Thanks for opting in',
                        ]],
                        'else' => [[
                            'type' => 'TextCaption',
                            'text' => 'You can opt in later',
                        ]],
                    ]],
                ],
            ]],
        ]);

        $field = $local['screens'][0]['fields'][0];

        $this->assertSame('if_condition', $field['type']);
        $this->assertSame('${form.optin} == true', $field['condition']);
        $this->assertSame('body', $field['then_children'][0]['type']);
        $this->assertSame('caption', $field['else_children'][0]['type']);
    }
}
