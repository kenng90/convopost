<?php

namespace Tests\Unit;

use App\Services\WhatsappFlowDynamicDataBuilder;
use Tests\TestCase;

class WhatsappFlowDynamicDataBuilderTest extends TestCase
{
    public function test_it_builds_option_list_schema(): void
    {
        $builder = new WhatsappFlowDynamicDataBuilder;

        $schema = $builder->entriesToMetaSchema([
            [
                'key' => 'available_slots',
                'type' => 'option_list',
                'example_items' => [['id' => '1', 'title' => '09:00']],
            ],
            [
                'key' => 'is_dropdown_visible',
                'type' => 'boolean',
                'example' => false,
            ],
        ]);

        $this->assertArrayHasKey('available_slots', $schema);
        $this->assertSame('array', $schema['available_slots']['type']);
        $this->assertSame('string', $schema['available_slots']['items']['properties']['id']['type']);
        $this->assertFalse($schema['is_dropdown_visible']['__example__']);
    }

    public function test_it_handles_object_array_examples_without_string_cast_error(): void
    {
        $builder = new WhatsappFlowDynamicDataBuilder;

        $schema = $builder->entriesToMetaSchema([
            [
                'key' => 'additional_applicants',
                'type' => 'string',
                'example' => [
                    ['id' => 'Spouse', 'dob' => '11/05/1995', 'tobacco' => 'No'],
                ],
            ],
        ]);

        $this->assertSame('array', $schema['additional_applicants']['type']);
        $this->assertArrayHasKey('dob', $schema['additional_applicants']['items']['properties']);
        $this->assertSame('Spouse', $schema['additional_applicants']['__example__'][0]['id']);
    }

    public function test_meta_schema_to_entries_distinguishes_option_list_from_object_array(): void
    {
        $builder = new WhatsappFlowDynamicDataBuilder;

        $entries = $builder->metaSchemaToEntries([
            'cover' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                    ],
                ],
                '__example__' => [['id' => 'a', 'title' => 'A']],
            ],
            'additional_applicants' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'dob' => ['type' => 'string'],
                    ],
                ],
                '__example__' => [['id' => 'Spouse', 'dob' => '11/05/1995']],
            ],
        ]);

        $this->assertSame('option_list', $entries[0]['type']);
        $this->assertSame('object_array', $entries[1]['type']);
        $this->assertSame('Spouse', $entries[1]['example'][0]['id']);
    }
}
