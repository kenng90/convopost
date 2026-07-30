<?php

namespace App\Services;

/**
 * Best-effort converter from official Meta Flow JSON to the local builder format.
 */
class WhatsappMetaFlowJsonImporter
{
    public function __construct(
        private readonly WhatsappFlowDynamicDataBuilder $dynamicDataBuilder,
    ) {
    }

    private const TYPE_MAP = [
        'TextInput' => 'text',
        'TextArea' => 'textarea',
        'RadioButtonsGroup' => 'radio',
        'CheckboxGroup' => 'checkbox',
        'Dropdown' => 'dropdown',
        'DatePicker' => 'date',
        'OptIn' => 'optin',
        'ChipsSelector' => 'chips',
        'PhotoPicker' => 'photo_picker',
        'DocumentPicker' => 'document_picker',
    ];

    /**
     * @param  array<string, mixed>  $metaJson
     * @return array<string, mixed>
     */
    public function toLocalFormat(array $metaJson): array
    {
        $screens = [];

        foreach ($metaJson['screens'] ?? [] as $index => $screen) {
            if (! is_array($screen)) {
                continue;
            }

            $screenData = is_array($screen['data'] ?? null) ? $screen['data'] : [];
            $fields = $this->extractFieldsFromLayout($screen['layout']['children'] ?? [], $screenData);

            $screens[] = [
                'id' => (string) ($screen['id'] ?? 'SCREEN_'.($index + 1)),
                'title' => (string) ($screen['title'] ?? 'Screen '.($index + 1)),
                'terminal' => (bool) ($screen['terminal'] ?? $screen['is_terminal'] ?? false),
                'refresh_on_back' => $screen['refresh_on_back'] ?? null,
                'endpoint_template' => $screen['endpoint_template'] ?? null,
                'dynamic_data' => $this->metaDataToEntries($screenData),
                'fields' => $fields,
            ];
        }

        return [
            'screens' => $screens,
            'version' => $metaJson['version'] ?? null,
            'data_api_version' => $metaJson['data_api_version'] ?? null,
            'imported_from_meta' => true,
        ];
    }

    /**
     * @param  list<mixed>  $children
     * @param  array<string, mixed>  $screenData
     * @return list<array<string, mixed>>
     */
    private function extractFieldsFromLayout(array $children, array $screenData): array
    {
        $fields = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            if (($child['type'] ?? '') === 'Form') {
                foreach ($child['children'] ?? [] as $formChild) {
                    $field = $this->parseComponent($formChild, $screenData);
                    if ($field) {
                        $fields[] = $field;
                    }
                }

                continue;
            }

            $field = $this->parseComponent($child, $screenData);
            if ($field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $screenData
     * @return array<string, mixed>|null
     */
    private function parseComponent(array $component, array $screenData): ?array
    {
        $type = (string) ($component['type'] ?? '');
        $localType = self::TYPE_MAP[$type] ?? null;

        if (! $localType) {
            return null;
        }

        $name = (string) ($component['name'] ?? '');
        $label = (string) ($component['label'] ?? ucfirst(str_replace('_', ' ', $localType)));

        $field = [
            'id' => $this->deriveFieldId($name, $localType),
            'type' => $localType,
            'label' => $label,
            'meta_name' => $name !== '' ? $name : null,
            'required' => (bool) ($component['required'] ?? false),
            'options' => $this->parseOptions($component),
        ];

        if ($name !== '' && isset($screenData[$name])) {
            $field['dynamic_data_source'] = true;
            $field['data_source_key'] = $name;
        }

        return $field;
    }

    /**
     * @param  array<string, mixed>  $component
     * @return list<array<string, mixed>>
     */
    private function parseOptions(array $component): array
    {
        $raw = $component['data-source'] ?? $component['options'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $options[] = [
                'id' => (string) ($item['id'] ?? $item['value'] ?? ''),
                'label' => (string) ($item['title'] ?? $item['label'] ?? $item['id'] ?? ''),
                'value' => (string) ($item['id'] ?? $item['value'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $screenData  Meta screen.data schema (key => {type, __example__})
     * @return list<array<string, mixed>>
     */
    private function metaDataToEntries(array $screenData): array
    {
        return $this->dynamicDataBuilder->metaSchemaToEntries($screenData);
    }

    private function deriveFieldId(string $name, string $localType): int|string
    {
        if ($name !== '' && preg_match('/_(\d+)$/', $name, $matches)) {
            return (int) $matches[1];
        }

        if ($name !== '') {
            return crc32($name) & 0x7FFFFFFF;
        }

        return 1;
    }
}
