<?php

namespace App\Services;

/**
 * Converts builder "dynamic data" entries into Meta Flow screen data schema.
 */
class WhatsappFlowDynamicDataBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<string, array<string, mixed>>
     */
    public function entriesToMetaSchema(array $entries): array
    {
        $schema = [];

        foreach ($entries as $entry) {
            $key = trim((string) ($entry['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $schema[$key] = $this->entryToMetaField($entry);
        }

        return $schema;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    public function entryToMetaField(array $entry): array
    {
        $type = $entry['type'] ?? 'string';
        $example = $entry['example'] ?? null;

        if ($type === 'string' && is_array($example)) {
            $type = 'object_array';
        }

        return match ($type) {
            'boolean' => [
                'type' => 'boolean',
                '__example__' => filter_var($entry['example'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            'number' => [
                'type' => 'number',
                '__example__' => (float) ($entry['example'] ?? 0),
            ],
            'option_list' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                    ],
                ],
                '__example__' => $this->normalizeOptionExamples($entry['example_items'] ?? []),
            ],
            'object_array' => $this->buildObjectArrayMetaField($entry),
            default => [
                'type' => 'string',
                '__example__' => $this->scalarExampleToString($example),
            ],
        };
    }

    /**
     * Reverse Meta screen data schema into builder entries (for import).
     *
     * @param  array<string, mixed>  $metaData
     * @return array<int, array<string, mixed>>
     */
    public function metaSchemaToEntries(array $metaData): array
    {
        $entries = [];

        foreach ($metaData as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $metaType = $definition['type'] ?? 'string';
            $example = $definition['__example__'] ?? null;

            if ($metaType === 'boolean') {
                $entries[] = ['key' => $key, 'type' => 'boolean', 'example' => (bool) $example];
            } elseif ($metaType === 'number') {
                $entries[] = ['key' => $key, 'type' => 'number', 'example' => $example ?? 0];
            } elseif ($metaType === 'array' && $this->isOptionListSchema($definition)) {
                $entries[] = [
                    'key' => $key,
                    'type' => 'option_list',
                    'example_items' => is_array($example) ? $example : [],
                ];
            } elseif ($metaType === 'array') {
                $entries[] = [
                    'key' => $key,
                    'type' => 'object_array',
                    'example' => is_array($example) ? $example : [],
                    'item_properties' => $definition['items']['properties'] ?? null,
                ];
            } else {
                $entries[] = [
                    'key' => $key,
                    'type' => 'string',
                    'example' => $this->scalarExampleToString($example),
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function buildObjectArrayMetaField(array $entry): array
    {
        $example = $entry['example'] ?? [];
        if (! is_array($example)) {
            $example = [];
        }

        $properties = $entry['item_properties'] ?? null;
        if (! is_array($properties) || $properties === []) {
            $properties = $this->inferPropertiesFromExample($example);
        }

        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => $properties,
            ],
            '__example__' => $example,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function isOptionListSchema(array $definition): bool
    {
        $properties = $definition['items']['properties'] ?? null;
        if (! is_array($properties)) {
            return false;
        }

        $keys = array_keys($properties);
        sort($keys);

        return $keys === ['id', 'title'];
    }

    /**
     * @param  array<int, mixed>  $example
     * @return array<string, array<string, string>>
     */
    private function inferPropertiesFromExample(array $example): array
    {
        $sample = $example[0] ?? null;
        if (! is_array($sample)) {
            return [
                'id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
            ];
        }

        $properties = [];
        foreach ($sample as $property => $value) {
            $properties[(string) $property] = ['type' => $this->inferJsonType($value)];
        }

        return $properties;
    }

    private function inferJsonType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value), is_float($value) => 'number',
            is_array($value) => 'array',
            default => 'string',
        };
    }

    private function scalarExampleToString(mixed $example): string
    {
        if (is_array($example) || is_object($example)) {
            return '';
        }

        return (string) ($example ?? '');
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, string>>
     */
    private function normalizeOptionExamples(array $items): array
    {
        if ($items === []) {
            return [];
        }

        return array_values(array_map(fn ($item) => [
            'id' => (string) ($item['id'] ?? $item['value'] ?? '1'),
            'title' => (string) ($item['title'] ?? $item['label'] ?? 'Option'),
        ], $items));
    }
}
