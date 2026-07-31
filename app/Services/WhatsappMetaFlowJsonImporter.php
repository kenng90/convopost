<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Converts official Meta Flow JSON to the local builder format.
 *
 * Mirrors the importJson() logic in flows-builder.blade.php so Meta API import
 * and paste-JSON import produce the same local schema.
 */
class WhatsappMetaFlowJsonImporter
{
    private int $nextFieldId = 1;

    private const TYPE_MAP = [
        'TextInput' => 'text',
        'TextArea' => 'textarea',
        'RadioButtonsGroup' => 'radio',
        'CheckboxGroup' => 'checkbox',
        'Dropdown' => 'select',
        'DatePicker' => 'date',
        'CalendarPicker' => 'calendar',
        'ChipsSelector' => 'chips',
        'PhotoPicker' => 'photo_picker',
        'DocumentPicker' => 'document_picker',
        'NavigationList' => 'navigation_list',
        'If' => 'if_condition',
        'Switch' => 'switch',
        'OptIn' => 'optin',
        'TextHeading' => 'heading',
        'TextSubheading' => 'subheading',
        'TextBody' => 'body',
        'TextCaption' => 'caption',
        'RichText' => 'richtext',
        'Image' => 'image',
        'ImageCarousel' => 'image_carousel',
        'EmbeddedLink' => 'embedded_link',
        'Footer' => 'footer',
    ];

    private const DEFAULT_LABELS = [
        'text' => 'Text Input',
        'textarea' => 'Multi-line Text',
        'radio' => 'Select One',
        'checkbox' => 'Select Multiple',
        'select' => 'Dropdown',
        'date' => 'Select Date',
        'chips' => 'Quick Select',
        'optin' => 'I agree to terms',
        'heading' => 'Page Heading',
        'subheading' => 'Subheading',
        'body' => 'Body text',
        'caption' => 'Caption',
        'richtext' => 'Rich text',
        'image' => 'Image',
        'image_carousel' => 'Image Carousel',
        'calendar' => 'Select dates',
        'photo_picker' => 'Upload photo',
        'document_picker' => 'Upload document',
        'navigation_list' => 'Choose option',
        'if_condition' => 'Conditional block',
        'switch' => 'Switch',
        'embedded_link' => 'Open Link',
        'footer' => 'Continue',
        'button' => 'Submit',
    ];

    private const META_TYPE_MAP = [
        'text' => ['string', 'example text'],
        'textarea' => ['string', 'example text'],
        'radio' => ['string', 'option1'],
        'select' => ['string', 'option1'],
        'chips' => ['array', ['option1']],
        'date' => ['string', '2026-01-01'],
        'calendar' => ['string', '2026-01-01'],
        'checkbox' => ['array', ['option1']],
        'optin' => ['boolean', true],
        'photo_picker' => ['array', []],
        'document_picker' => ['array', []],
    ];

    public function __construct(
        private readonly WhatsappFlowDynamicDataBuilder $dynamicDataBuilder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metaJson
     * @return array<string, mixed>
     */
    public function toLocalFormat(array $metaJson): array
    {
        $this->nextFieldId = 1;
        $screens = [];

        foreach ($metaJson['screens'] ?? [] as $index => $screen) {
            if (! is_array($screen)) {
                continue;
            }

            $screenData = is_array($screen['data'] ?? null) ? $screen['data'] : [];
            $dynamicData = $this->metaDataToEntries($screenData);
            $fields = $this->flattenLayout($screen['layout']['children'] ?? [], $screenData);

            $screenEntry = [
                'id' => (string) ($screen['id'] ?? 'SCREEN_'.($index + 1)),
                'title' => (string) ($screen['title'] ?? 'Screen '.($index + 1)),
                'terminal' => (bool) ($screen['terminal'] ?? $screen['is_terminal'] ?? false),
                'refresh_on_back' => $screen['refresh_on_back'] ?? null,
                'endpoint_template' => $screen['endpoint_template'] ?? null,
                'dynamic_data' => $dynamicData,
                'fields' => $fields,
            ];

            $this->hydrateImportedScreenFields($screenEntry, $screenData);

            $screens[] = $screenEntry;
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
    private function flattenLayout(array $children, array $screenData): array
    {
        $fields = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $type = (string) ($child['type'] ?? '');

            if ($type === 'Form') {
                foreach ($child['children'] ?? [] as $formChild) {
                    $field = $this->parseComponent(is_array($formChild) ? $formChild : [], $screenData);
                    if ($field !== null) {
                        $fields[] = $field;
                    }
                }

                continue;
            }

            $field = $this->parseComponent($child, $screenData);
            if ($field !== null) {
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
        $metaType = (string) ($component['type'] ?? '');
        $builderType = self::TYPE_MAP[$metaType] ?? null;

        if ($builderType === null) {
            return null;
        }

        [$metaTypeName, $metaExample] = self::META_TYPE_MAP[$builderType] ?? ['string', 'example'];

        $label = (string) ($component['label']
            ?? $component['text']
            ?? self::DEFAULT_LABELS[$builderType]
            ?? $builderType);

        $placeholder = is_array($component['text'] ?? null)
            ? implode("\n", $component['text'])
            : (string) ($component['text'] ?? '');

        $field = [
            'id' => $this->allocateFieldId(),
            'type' => $builderType,
            'label' => $label,
            'placeholder' => $placeholder,
            'required' => is_bool($component['required'] ?? null) ? $component['required'] : false,
            'meta_type' => $metaTypeName,
            'meta_example' => $metaExample,
            'options' => [],
        ];

        if (! empty($component['name'])) {
            $field['meta_name'] = (string) $component['name'];
        }

        if (is_string($component['required'] ?? null)) {
            $field['required_binding'] = $component['required'];
        }

        if (! empty($component['visible'])) {
            $field['visible_binding'] = $component['visible'];
        }

        if (is_array($component['on-select-action'] ?? null)) {
            $field['on_select_action'] = $component['on-select-action']['name'] ?? null;
            $field['on_select_payload'] = $component['on-select-action']['payload'] ?? [];
        }

        if (in_array($builderType, ['radio', 'checkbox', 'select', 'chips'], true)) {
            $this->applyImportedDataSource($field, $component['data-source'] ?? null, $screenData);
        }

        match ($builderType) {
            'text' => $this->applyTextInputMeta($field, $component),
            'textarea' => $this->applyTextAreaMeta($field, $component),
            'body', 'caption' => $this->applyBodyMeta($field, $component),
            'image' => $this->applyImageMeta($field, $component),
            'image_carousel' => $this->applyImageCarouselMeta($field, $component),
            'embedded_link' => $this->applyEmbeddedLinkMeta($field, $component),
            'optin' => $this->applyOptInMeta($field, $component),
            'calendar' => $this->applyCalendarMeta($field, $component),
            'navigation_list' => $this->applyNavigationListMeta($field, $component),
            'if_condition' => $this->applyIfConditionMeta($field, $component, $screenData),
            'switch' => $this->applySwitchMeta($field, $component, $screenData),
            'footer' => $this->applyFooterMeta($field, $component),
            default => null,
        };

        return $field;
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyTextInputMeta(array &$field, array $component): void
    {
        $field['input_type'] = $component['input-type'] ?? 'text';
        $field['helper_text'] = $component['helper-text'] ?? '';
        $field['sensitive'] = (bool) ($component['sensitive'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyTextAreaMeta(array &$field, array $component): void
    {
        $field['max_length'] = $component['max-length'] ?? 600;
        $field['helper_text'] = $component['helper-text'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyBodyMeta(array &$field, array $component): void
    {
        $field['markdown'] = (bool) ($component['markdown'] ?? false);
        $field['placeholder'] = $component['text'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyImageMeta(array &$field, array $component): void
    {
        $field['image_url'] = $component['src'] ?? '';
        $field['height'] = $component['height'] ?? 300;
        $field['scale_type'] = $component['scale-type'] ?? 'contain';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyImageCarouselMeta(array &$field, array $component): void
    {
        $field['images'] = array_map(
            fn (array $image) => [
                'src' => $image['src'] ?? '',
                'alt_text' => $image['alt-text'] ?? '',
            ],
            array_filter($component['images'] ?? [], 'is_array'),
        );
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyEmbeddedLinkMeta(array &$field, array $component): void
    {
        $action = is_array($component['on-click-action'] ?? null) ? $component['on-click-action'] : [];
        $field['url'] = $action['url'] ?? ($action['payload']['url'] ?? '');
        $field['button_label'] = $component['text'] ?? 'Open Link';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyOptInMeta(array &$field, array $component): void
    {
        $action = is_array($component['on-click-action'] ?? null) ? $component['on-click-action'] : [];
        if (($action['name'] ?? '') === 'open_url') {
            $field['read_more_url'] = $action['url'] ?? '';
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyCalendarMeta(array &$field, array $component): void
    {
        $field['calendar_mode'] = $component['mode'] ?? 'single';
        $field['helper_text'] = $component['helper-text'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyNavigationListMeta(array &$field, array $component): void
    {
        $field['list_items'] = array_map(function (array $item) {
            $action = is_array($item['on-click-action'] ?? null) ? $item['on-click-action'] : [];
            $mainContent = is_array($item['main-content'] ?? null) ? $item['main-content'] : [];

            return [
                'id' => $item['id'] ?? '',
                'title' => $mainContent['title'] ?? ($item['id'] ?? ''),
                'description' => $mainContent['description'] ?? '',
                'next_screen_id' => $action['next']['name'] ?? '',
                'on_click_action' => $action['name'] ?? 'navigate',
                'on_click_payload' => $action['payload'] ?? [],
            ];
        }, array_filter($component['list-items'] ?? [], 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $screenData
     */
    private function applyIfConditionMeta(array &$field, array $component, array $screenData): void
    {
        $field['condition'] = $component['condition'] ?? '${true}';
        $field['then_children'] = $this->parseChildComponents($component['then'] ?? [], $screenData);
        $field['else_children'] = $this->parseChildComponents($component['else'] ?? [], $screenData);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $screenData
     */
    private function applySwitchMeta(array &$field, array $component, array $screenData): void
    {
        $field['switch_value'] = $component['value'] ?? '${data.value}';
        $field['cases'] = [];

        foreach ($component['cases'] ?? [] as $key => $children) {
            if (! is_array($children)) {
                continue;
            }

            $field['cases'][] = [
                'key' => (string) $key,
                'children' => $this->parseChildComponents($children, $screenData),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $component
     */
    private function applyFooterMeta(array &$field, array $component): void
    {
        $action = is_array($component['on-click-action'] ?? null) ? $component['on-click-action'] : [];

        $field['type'] = 'footer';
        $field['label'] = $component['label'] ?? 'Continue';
        $field['on_click_action'] = $action['name'] ?? null;
        $field['on_click_payload'] = $action['payload'] ?? [];
        $field['navigate_next'] = $action['next']['name'] ?? '';
    }

    /**
     * @param  list<mixed>  $children
     * @param  array<string, mixed>  $screenData
     * @return list<array<string, mixed>>
     */
    private function parseChildComponents(array $children, array $screenData): array
    {
        $parsed = [];

        foreach ($children as $child) {
            if (! is_array($child)) {
                continue;
            }

            $field = $this->parseComponent($child, $screenData);
            if ($field !== null) {
                $parsed[] = $field;
            }
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function applyImportedDataSource(array &$field, mixed $dataSource, array $screenData): void
    {
        $dynamicKey = $this->extractDynamicDataKey($dataSource);

        if ($dynamicKey !== null) {
            $field['dynamic_data_source'] = true;
            $field['data_source_key'] = $dynamicKey;
            $field['options'] = $this->exampleOptionsFromScreenData($screenData, $dynamicKey);
            $field['imported_dynamic_source'] = empty($field['options']);

            return;
        }

        if (is_array($dataSource)) {
            $field['dynamic_data_source'] = false;
            $field['options'] = $this->metaOptionItemsToBuilderOptions($dataSource);
            $field['imported_dynamic_source'] = empty($field['options']);

            return;
        }

        $field['dynamic_data_source'] = false;
        $field['options'] = [];
        $field['imported_dynamic_source'] = true;
    }

    private function extractDynamicDataKey(mixed $dataSource): ?string
    {
        if (! is_string($dataSource)) {
            return null;
        }

        if (preg_match('/^\$\{data\.([^}]+)\}$/', $dataSource, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $screenData
     * @return list<array<string, mixed>>
     */
    private function exampleOptionsFromScreenData(array $screenData, string $key): array
    {
        $def = $screenData[$key] ?? null;
        if (! is_array($def)) {
            return [];
        }

        return $this->metaOptionItemsToBuilderOptions(
            $this->normalizeMetaExampleItems($def['__example__'] ?? [])
        );
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    private function normalizeMetaExampleItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized[] = [
                'id' => (string) ($item['id'] ?? $item['value'] ?? 'option_'.($index + 1)),
                'title' => (string) ($item['title'] ?? $item['label'] ?? $item['description'] ?? $item['id'] ?? 'Option '.($index + 1)),
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function metaOptionItemsToBuilderOptions(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $options = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $value = (string) ($item['id'] ?? $item['value'] ?? 'option_'.($index + 1));

            $options[] = [
                'id' => (string) Str::uuid(),
                'label' => (string) ($item['title'] ?? $item['label'] ?? $item['description'] ?? $value),
                'value' => $value,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $screen
     * @param  array<string, mixed>  $screenData
     */
    private function hydrateImportedScreenFields(array &$screen, array $screenData): void
    {
        if (! isset($screen['fields']) || ! is_array($screen['fields'])) {
            $screen['fields'] = [];
        }

        $this->walkImportedFields($screen['fields'], $screen, $screenData);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $screen
     * @param  array<string, mixed>  $screenData
     */
    private function walkImportedFields(array &$fields, array &$screen, array $screenData): void
    {
        foreach ($fields as &$field) {
            if (in_array($field['type'] ?? '', ['radio', 'checkbox', 'select', 'chips'], true)) {
                if (! empty($field['dynamic_data_source']) && ! empty($field['data_source_key']) && empty($field['options'])) {
                    $entry = collect($screen['dynamic_data'] ?? [])->firstWhere('key', $field['data_source_key']);

                    if (! empty($entry['example_items'])) {
                        $field['options'] = $this->metaOptionItemsToBuilderOptions($entry['example_items']);
                    } else {
                        $field['options'] = $this->exampleOptionsFromScreenData($screenData, $field['data_source_key']);
                    }
                }
            }

            if (($field['type'] ?? '') === 'if_condition') {
                if (isset($field['then_children']) && is_array($field['then_children'])) {
                    $this->walkImportedFields($field['then_children'], $screen, $screenData);
                }
                if (isset($field['else_children']) && is_array($field['else_children'])) {
                    $this->walkImportedFields($field['else_children'], $screen, $screenData);
                }
            }

            if (($field['type'] ?? '') === 'switch') {
                foreach ($field['cases'] ?? [] as &$case) {
                    if (isset($case['children']) && is_array($case['children'])) {
                        $this->walkImportedFields($case['children'], $screen, $screenData);
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $screenData
     * @return list<array<string, mixed>>
     */
    private function metaDataToEntries(array $screenData): array
    {
        return $this->dynamicDataBuilder->metaSchemaToEntries($screenData);
    }

    private function allocateFieldId(): int
    {
        return $this->nextFieldId++;
    }
}
