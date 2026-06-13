<?php

namespace App\Services;

/**
 * Maps builder field definitions to Meta WhatsApp Flow JSON components.
 */
class WhatsappFlowComponentMapper
{
    public const MAX_COMPONENTS_PER_SCREEN = 50;

    public const INPUT_FIELD_TYPES = [
        'text', 'textarea', 'radio', 'checkbox', 'select', 'date', 'chips', 'optin',
        'calendar', 'photo_picker', 'document_picker', 'media_upload',
    ];

    public const DISPLAY_ONLY_TYPES = [
        'heading', 'subheading', 'body', 'caption', 'richtext', 'image', 'image_carousel',
    ];

    public const FOOTER_TYPES = ['navigate', 'button', 'footer'];

    public const NAVIGATE_PAYLOAD_EXCLUDED = ['photo_picker', 'document_picker'];

    public function getMetaDataType(string $fieldType): string
    {
        return match ($fieldType) {
            'date', 'calendar' => 'string',
            'checkbox', 'chips' => 'array',
            'optin' => 'boolean',
            'photo_picker', 'document_picker' => 'array',
            default => 'string',
        };
    }

    public function getMetaDataExample(string $fieldType): mixed
    {
        return match ($fieldType) {
            'date', 'calendar' => '2026-01-01',
            'checkbox', 'chips' => ['option1'],
            'optin' => true,
            'photo_picker', 'document_picker' => [],
            default => 'example',
        };
    }

    public function getComponentName(array $field): ?string
    {
        if (! empty($field['meta_name'])) {
            return (string) $field['meta_name'];
        }

        $type = $field['type'] ?? '';
        $fieldId = $field['id'] ?? '';

        if ($fieldId === '' || $fieldId === null) {
            return null;
        }

        if (! empty($field['dynamic_data_source']) && ! empty($field['data_source_key'])) {
            return (string) $field['data_source_key'];
        }

        return match ($type) {
            'text' => 'text_'.$fieldId,
            'textarea' => 'textarea_'.$fieldId,
            'radio' => 'radio_'.$fieldId,
            'checkbox' => 'checkbox_'.$fieldId,
            'select' => 'select_'.$fieldId,
            'date' => 'date_'.$fieldId,
            'calendar' => 'calendar_'.$fieldId,
            'chips' => 'chips_'.$fieldId,
            'optin' => 'optin_'.$fieldId,
            'photo_picker', 'media_upload' => 'photo_'.$fieldId,
            'document_picker' => 'document_'.$fieldId,
            default => null,
        };
    }

    public function getPayloadKey(array $field): ?string
    {
        if (! empty($field['payload_key'])) {
            return (string) $field['payload_key'];
        }

        return $this->getComponentName($field);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $previousScreenFields
     * @return array<int, array<string, mixed>>
     */
    public function convertFieldsToComponents(
        array $fields,
        ?string $nextScreenId,
        bool $isTerminal,
        array $previousScreenFields,
        callable $resolveImageSrc,
        array $currentScreenDataKeys = [],
        array $currentScreenDynamicKeys = [],
        array $nextScreenDynamicKeys = [],
        array $nextScreenInputFields = [],
        bool $usesEndpointFlow = false
    ): array {
        $navListFields = array_values(array_filter(
            $fields,
            fn ($f) => ($f['type'] ?? '') === 'navigation_list'
        ));

        if ($navListFields !== []) {
            return array_map(
                fn ($f) => $this->convertFieldToComponent($f, $nextScreenId, $isTerminal, $resolveImageSrc),
                $navListFields
            );
        }

        $outsideChildren = [];
        $inputFields = [];
        $footerFields = [];
        $logicFields = [];

        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            if (in_array($type, self::DISPLAY_ONLY_TYPES, true)) {
                $outsideChildren[] = $this->convertFieldToComponent($field, $nextScreenId, $isTerminal, $resolveImageSrc);
            } elseif (in_array($type, self::FOOTER_TYPES, true)) {
                $footerFields[] = $field;
            } elseif (in_array($type, ['if_condition', 'switch'], true)) {
                $logicFields[] = $field;
            } else {
                $inputFields[] = $field;
            }
        }

        $outsideChildren = array_merge(
            $outsideChildren,
            array_map(
                fn ($f) => $this->convertFieldToComponent($f, $nextScreenId, $isTerminal, $resolveImageSrc),
                $logicFields
            )
        );

        if ($inputFields === [] && $footerFields === []) {
            return $outsideChildren;
        }

        $actionPayload = $this->buildNavigatePayload(
            $inputFields,
            $currentScreenDataKeys,
            $currentScreenDynamicKeys,
            $nextScreenDynamicKeys,
            $nextScreenInputFields,
            $isTerminal,
            $previousScreenFields
        );

        $convertedInputs = array_map(
            fn ($f) => $this->convertFieldToComponent($f, $nextScreenId, $isTerminal, $resolveImageSrc),
            $inputFields
        );

        $convertedFooters = array_map(
            function ($field) use (
                $nextScreenId,
                $isTerminal,
                $actionPayload,
                $inputFields,
                $currentScreenDataKeys,
                $currentScreenDynamicKeys,
                $usesEndpointFlow
            ) {
                $component = $this->convertFieldToComponent($field, $nextScreenId, $isTerminal, fn () => '');
                $actionName = $component['on-click-action']['name'] ?? '';
                $preservedPayload = $field['on_click_payload'] ?? null;

                if (is_array($preservedPayload) && $preservedPayload !== []) {
                    $component['on-click-action']['payload'] = $this->reconcileNavigatePayload(
                        $preservedPayload,
                        $actionPayload,
                        $currentScreenDataKeys,
                        $isTerminal
                    );

                    $component['on-click-action']['name'] = $this->resolveFooterClickActionName(
                        $field,
                        $isTerminal,
                        $usesEndpointFlow,
                        $currentScreenDynamicKeys,
                        (string) ($component['on-click-action']['name'] ?? 'navigate')
                    );

                    if (! empty($field['navigate_next']) && ($component['on-click-action']['name'] ?? '') === 'navigate') {
                        $component['on-click-action']['next'] = [
                            'type' => 'screen',
                            'name' => (string) $field['navigate_next'],
                        ];
                    }
                } else {
                    $payloadToInject = empty($actionPayload) ? new \stdClass() : $actionPayload;

                    $hasMediaOnScreen = collect($inputFields)->contains(
                        fn ($f) => in_array($f['type'] ?? '', self::NAVIGATE_PAYLOAD_EXCLUDED, true)
                    );

                    if (! $isTerminal && $hasMediaOnScreen && $actionName === 'navigate') {
                        $component['on-click-action']['name'] = 'data_exchange';
                    } else {
                        $component['on-click-action']['name'] = $this->resolveFooterClickActionName(
                            $field,
                            $isTerminal,
                            $usesEndpointFlow,
                            $currentScreenDynamicKeys,
                            (string) ($component['on-click-action']['name'] ?? 'navigate')
                        );
                    }

                    if ($isTerminal && $actionName === 'complete') {
                        $component['on-click-action']['payload'] = $payloadToInject;
                    } elseif (! $isTerminal && in_array($component['on-click-action']['name'] ?? '', ['navigate', 'data_exchange'], true)) {
                        $component['on-click-action']['payload'] = $payloadToInject;
                    }
                }

                if (isset($component['on-click-action']) && is_array($component['on-click-action'])) {
                    $component['on-click-action'] = $this->normalizeClickAction($component['on-click-action']);
                }

                return $component;
            },
            $footerFields
        );

        $formComponent = [
            'type' => 'Form',
            'name' => 'main_form',
            'children' => array_merge($convertedInputs, $convertedFooters),
        ];

        return array_merge($outsideChildren, [$formComponent]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputFields
     * @param  array<int, array<string, mixed>>  $previousScreenFields
     * @return array<string, string>
     */
    public function buildActionPayload(array $inputFields, array $previousScreenFields, bool $isTerminal): array
    {
        $actionPayload = [];

        foreach ($inputFields as $field) {
            $componentName = $this->getComponentName($field);
            $payloadKey = $this->getPayloadKey($field);
            if (! $componentName || ! $payloadKey) {
                continue;
            }

            if (! $isTerminal && in_array($field['type'] ?? '', self::NAVIGATE_PAYLOAD_EXCLUDED, true)) {
                continue;
            }

            $actionPayload[$payloadKey] = '${form.'.$componentName.'}';
        }

        foreach ($previousScreenFields as $field) {
            $componentName = $this->getComponentName($field);
            $payloadKey = $this->getPayloadKey($field);
            if ($componentName && $payloadKey) {
                $actionPayload[$payloadKey] = '${data.'.$componentName.'}';
            }
        }

        return $actionPayload;
    }

    /**
     * Build a navigate/complete payload keyed to the next screen's data model.
     *
     * @param  array<int, array<string, mixed>>  $inputFields
     * @param  array<int, string>  $currentScreenDataKeys
     * @param  array<int, string>  $currentScreenDynamicKeys
     * @param  array<int, string>  $nextScreenDynamicKeys
     * @param  array<int, array<string, mixed>>  $nextScreenInputFields
     * @param  array<int, array<string, mixed>>  $previousScreenFields
     * @return array<string, string>
     */
    public function buildNavigatePayload(
        array $inputFields,
        array $currentScreenDataKeys,
        array $currentScreenDynamicKeys,
        array $nextScreenDynamicKeys,
        array $nextScreenInputFields,
        bool $isTerminal,
        array $previousScreenFields = []
    ): array {
        if ($isTerminal) {
            return $this->buildActionPayload($inputFields, $previousScreenFields, true);
        }

        if ($nextScreenDynamicKeys === []) {
            return $this->buildActionPayload($inputFields, $previousScreenFields, false);
        }

        $semanticAssignments = $this->buildSemanticFieldAssignments(
            $inputFields,
            $currentScreenDynamicKeys,
            $nextScreenDynamicKeys,
            $nextScreenInputFields
        );

        $payload = [];

        foreach ($nextScreenDynamicKeys as $key) {
            $formField = $this->findFieldForPayloadKey($inputFields, $key)
                ?? ($semanticAssignments[$key] ?? null);

            if ($formField) {
                if (in_array($formField['type'] ?? '', self::NAVIGATE_PAYLOAD_EXCLUDED, true)) {
                    continue;
                }

                $componentName = $this->getComponentName($formField);
                if ($componentName) {
                    $payload[$key] = '${form.'.$componentName.'}';
                }

                continue;
            }

            $payload[$key] = '${data.'.$key.'}';
        }

        return $payload;
    }

    /**
     * Map semantic dynamic-data keys introduced on the next screen to unlabeled form fields on the current screen.
     *
     * @param  array<int, array<string, mixed>>  $inputFields
     * @param  array<int, string>  $currentScreenDynamicKeys
     * @param  array<int, string>  $nextScreenDynamicKeys
     * @param  array<int, array<string, mixed>>  $nextScreenInputFields
     * @return array<string, array<string, mixed>>
     */
    public function buildSemanticFieldAssignments(
        array $inputFields,
        array $currentScreenDynamicKeys,
        array $nextScreenDynamicKeys,
        array $nextScreenInputFields
    ): array {
        $newKeys = array_values(array_diff($nextScreenDynamicKeys, $currentScreenDynamicKeys));

        foreach ($nextScreenInputFields as $field) {
            $dataSourceKey = trim((string) ($field['data_source_key'] ?? ''));
            if ($dataSourceKey !== '') {
                $newKeys = array_values(array_filter(
                    $newKeys,
                    fn (string $key) => $key !== $dataSourceKey
                ));
            }
        }

        $unmappedFields = $this->unmappedInputFields($inputFields);
        $assignments = [];

        foreach ($newKeys as $index => $key) {
            if (! isset($unmappedFields[$index])) {
                continue;
            }

            $assignments[$key] = $unmappedFields[$index];
        }

        return $assignments;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inputFields
     * @return array<int, array<string, mixed>>
     */
    private function unmappedInputFields(array $inputFields): array
    {
        return array_values(array_filter($inputFields, function (array $field): bool {
            if (in_array($field['type'] ?? '', self::NAVIGATE_PAYLOAD_EXCLUDED, true)) {
                return false;
            }

            return trim((string) ($field['data_source_key'] ?? '')) === ''
                && trim((string) ($field['meta_name'] ?? '')) === ''
                && trim((string) ($field['payload_key'] ?? '')) === '';
        }));
    }

    /**
     * Merge an imported footer payload with the generated navigate payload.
     * Generated keys win so the next screen's data model is always satisfied.
     *
     * @param  array<string, string>  $preserved
     * @param  array<string, string>  $generated
     * @param  array<int, string>  $currentScreenDataKeys
     * @return array<string, string>
     */
    public function reconcileNavigatePayload(
        array $preserved,
        array $generated,
        array $currentScreenDataKeys,
        bool $isTerminal = false
    ): array {
        $result = $generated;

        foreach ($preserved as $key => $value) {
            if (! is_string($value)) {
                continue;
            }

            if (! $isTerminal && isset($result[$key])) {
                continue;
            }

            if (! $this->isValidPayloadBinding($value, $currentScreenDataKeys)) {
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $currentScreenDataKeys
     */
    private function isValidPayloadBinding(string $value, array $currentScreenDataKeys): bool
    {
        if (preg_match('/^\$\{data\.([^}]+)\}$/', $value, $matches)) {
            return in_array($matches[1], $currentScreenDataKeys, true);
        }

        return true;
    }

    /**
     * Ensure every ${data.key} referenced in components exists in the screen data schema.
     *
     * @param  array<string, array<string, mixed>>  $screenData
     * @param  array<int, array<string, mixed>>  $components
     * @return array<string, array<string, mixed>>
     */
    public function enrichScreenDataFromBindings(array $screenData, array $components): array
    {
        $json = json_encode($components);
        if ($json === false) {
            return $screenData;
        }

        if (! preg_match_all('/\$\{data\.([^}]+)\}/', $json, $matches)) {
            return $screenData;
        }

        foreach (array_unique($matches[1]) as $key) {
            if (isset($screenData[$key])) {
                continue;
            }

            $screenData[$key] = ['type' => 'string', '__example__' => ''];
        }

        return $screenData;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>|null
     */
    private function findFieldForPayloadKey(array $fields, string $key): ?array
    {
        foreach ($fields as $field) {
            if (($field['data_source_key'] ?? '') === $key) {
                return $field;
            }

            if (($field['meta_name'] ?? '') === $key) {
                return $field;
            }

            if (($field['payload_key'] ?? '') === $key) {
                return $field;
            }

            if ($this->getComponentName($field) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  callable(string): string  $resolveImageSrc
     */
    public function convertFieldToComponent(
        array $field,
        ?string $nextScreenId,
        bool $isTerminal,
        callable $resolveImageSrc
    ): array {
        $type = $field['type'] ?? 'text';
        $fieldId = $field['id'] ?? 'field';

        return match ($type) {
            'heading' => [
                'type' => 'TextHeading',
                'text' => $field['label'] ?? '',
            ],
            'subheading' => [
                'type' => 'TextSubheading',
                'text' => $field['label'] ?? '',
            ],
            'body' => array_filter([
                'type' => 'TextBody',
                'text' => $field['placeholder'] ?: ($field['label'] ?? ''),
                'markdown' => ($field['markdown'] ?? false) ? true : null,
            ], fn ($v) => $v !== null),
            'caption' => array_filter([
                'type' => 'TextCaption',
                'text' => $field['placeholder'] ?: ($field['label'] ?? ''),
                'markdown' => ($field['markdown'] ?? false) ? true : null,
            ], fn ($v) => $v !== null),
            'richtext' => [
                'type' => 'RichText',
                'text' => $this->richTextValue($field),
            ],
            'text' => $this->buildTextInput($field, $fieldId),
            'textarea' => $this->buildTextArea($field, $fieldId),
            'radio' => $this->buildSelectionGroup($field, $fieldId, 'RadioButtonsGroup'),
            'checkbox' => $this->buildSelectionGroup($field, $fieldId, 'CheckboxGroup'),
            'select' => $this->buildSelectionGroup($field, $fieldId, 'Dropdown'),
            'date' => $this->buildDatePicker($field, $fieldId),
            'calendar' => $this->buildCalendarPicker($field, $fieldId),
            'chips' => $this->buildChipsSelector($field, $fieldId),
            'optin' => $this->buildOptIn($field, $fieldId),
            'photo_picker', 'media_upload' => $this->buildPhotoPicker($field, $fieldId),
            'document_picker' => $this->buildDocumentPicker($field, $fieldId),
            'image' => [
                'type' => 'Image',
                'src' => $resolveImageSrc($field['image_url'] ?? ''),
                'height' => $field['height'] ?? 300,
                'scale-type' => $field['scale_type'] ?? 'contain',
            ],
            'image_carousel' => [
                'type' => 'ImageCarousel',
                'images' => ! empty($field['images'])
                    ? array_map(fn ($img) => [
                        'src' => $resolveImageSrc($img['src'] ?? $img['image_url'] ?? ''),
                        'alt-text' => $img['alt_text'] ?? $img['label'] ?? 'Image',
                    ], $field['images'])
                    : [['src' => '', 'alt-text' => 'Image']],
            ],
            'embedded_link' => [
                'type' => 'EmbeddedLink',
                'text' => $field['button_label'] ?? $field['label'] ?? 'Open Link',
                'on-click-action' => [
                    'name' => 'open_url',
                    'url' => $field['url'] ?? '',
                ],
            ],
            'navigate', 'footer' => $this->buildFooter($field, $nextScreenId, $isTerminal),
            'button' => $this->buildFooter($field, $nextScreenId, $isTerminal),
            'navigation_list' => $this->buildNavigationList($field, $fieldId),
            'if_condition' => $this->buildIfComponent($field, $nextScreenId, $isTerminal, $resolveImageSrc),
            'switch' => $this->buildSwitchComponent($field, $nextScreenId, $isTerminal, $resolveImageSrc),
            default => [
                'type' => 'TextBody',
                'text' => $field['label'] ?? 'Unknown Field Type: '.$type,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function collectPublishErrors(array $screens): array
    {
        $errors = [];

        $terminalScreens = array_values(array_filter(
            $screens,
            fn ($s) => ! empty($s['terminal'])
        ));

        if (count($terminalScreens) > 1) {
            $errors[] = 'Only one screen can be marked as terminal (submit screen).';
        }

        if ($screens !== [] && count($terminalScreens) === 0) {
            $errors[] = 'One screen must be marked as terminal (submit screen).';
        }

        foreach ($screens as $index => $screen) {
            $screenTitle = $screen['title'] ?? ('Screen '.($index + 1));
            $fields = $screen['fields'] ?? [];
            $componentCount = $this->countComponents($fields);

            if ($componentCount > self::MAX_COMPONENTS_PER_SCREEN) {
                $errors[] = "\"{$screenTitle}\": has {$componentCount} components (max ".self::MAX_COMPONENTS_PER_SCREEN.').';
            }

            if (empty($screen['title'])) {
                $errors[] = "Screen at index {$index} must have a title.";
            }

            if (! empty($screen['id']) && ! preg_match('/^[A-Za-z_]+$/', (string) $screen['id'])) {
                $errors[] = "Screen ID must contain only letters and underscores. Got: {$screen['id']}";
            }

            try {
                $this->validateRichTextRules($fields, $index);
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }

            $hasNavList = collect($fields)->contains(fn ($f) => ($f['type'] ?? '') === 'navigation_list');
            if ($hasNavList && count($fields) > 1) {
                $errors[] = "\"{$screenTitle}\": NavigationList screens cannot include other components.";
            }

            $photoCount = collect($fields)->where('type', 'photo_picker')->count();
            $docCount = collect($fields)->where('type', 'document_picker')->count();
            if ($photoCount > 1) {
                $errors[] = "\"{$screenTitle}\": only one PhotoPicker allowed per screen.";
            }
            if ($docCount > 1) {
                $errors[] = "\"{$screenTitle}\": only one DocumentPicker allowed per screen.";
            }
            if ($photoCount > 0 && $docCount > 0) {
                $errors[] = "\"{$screenTitle}\": PhotoPicker and DocumentPicker cannot be on the same screen.";
            }

            $footerCount = collect($fields)->filter(fn ($f) => in_array($f['type'] ?? '', self::FOOTER_TYPES, true))->count();
            if ($footerCount > 1) {
                $errors[] = "\"{$screenTitle}\": only one Footer allowed per screen.";
            }

            foreach ($fields as $field) {
                $errors = array_merge($errors, $this->validateField($field, $screenTitle, $index === count($screens) - 1));
            }
        }

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    public function collectPublishWarnings(array $screens): array
    {
        $warnings = [];

        if (count($screens) > 8) {
            $warnings[] = 'Flow has '.count($screens).' screens — Meta recommends keeping flows short (under ~5 minutes).';
        }

        foreach ($screens as $index => $screen) {
            $screenTitle = $screen['title'] ?? ('Screen '.($index + 1));
            $fields = $screen['fields'] ?? [];

            if ($this->countComponents($fields) > 8) {
                $warnings[] = "\"{$screenTitle}\": has many components — consider splitting the screen (Meta best practice).";
            }

            foreach ($fields as $field) {
                if (in_array($field['type'] ?? '', ['footer', 'button', 'navigate'], true)) {
                    $label = strtolower(trim($field['label'] ?? ''));
                    if (in_array($label, ['continue', 'next', 'submit', 'ok'], true) && $index < count($screens) - 1) {
                        $warnings[] = "\"{$screenTitle}\": CTA \"{$field['label']}\" is generic — use action-oriented text (e.g. \"Confirm booking\").";
                    }
                }

                if (in_array($field['type'] ?? '', ['radio', 'checkbox', 'select', 'chips'], true)) {
                    $optionCount = count($field['options'] ?? []);
                    if ($optionCount > 10) {
                        $warnings[] = "\"{$screenTitle}\": \"{$field['label']}\" has {$optionCount} options — Meta recommends ≤10 per screen.";
                    }
                    if (($field['type'] ?? '') === 'select' && $optionCount > 0 && $optionCount < 8) {
                        $warnings[] = "\"{$screenTitle}\": \"{$field['label']}\" has {$optionCount} options — consider RadioButtons for fewer than 8 choices.";
                    }
                }

                if (in_array($field['type'] ?? '', ['photo_picker', 'document_picker'], true) && $index < count($screens) - 1) {
                    $warnings[] = "\"{$screenTitle}\": media upload fields should be on the final screen or use data_exchange.";
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function countComponents(array $fields): int
    {
        $count = 0;
        foreach ($fields as $field) {
            $count++;
            if (($field['type'] ?? '') === 'if_condition') {
                $count += count($field['then_children'] ?? []) + count($field['else_children'] ?? []);
            }
            if (($field['type'] ?? '') === 'switch') {
                foreach ($field['cases'] ?? [] as $case) {
                    $count += count($case['children'] ?? []);
                }
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private function validateField(array $field, string $screenTitle, bool $isLastScreen): array
    {
        $errors = [];
        $type = $field['type'] ?? '';

        if ($type === 'heading' && strlen($field['label'] ?? '') > 80) {
            $errors[] = "\"{$screenTitle}\": Heading exceeds 80 characters.";
        }

        if ($type === 'embedded_link') {
            if (empty(trim($field['url'] ?? ''))) {
                $errors[] = "\"{$screenTitle}\": Embedded Link has an empty URL.";
            }
            if (strlen($field['button_label'] ?? $field['label'] ?? '') > 25) {
                $errors[] = "\"{$screenTitle}\": Embedded Link text exceeds 25 characters.";
            }
        }

        if ($type === 'image' && empty(trim($field['image_url'] ?? ''))) {
            $errors[] = "\"{$screenTitle}\": Image component has no source.";
        }

        if ($type === 'image_carousel') {
            $images = $field['images'] ?? [];
            if ($images === []) {
                $errors[] = "\"{$screenTitle}\": Image Carousel has no images.";
            }
            foreach ($images as $i => $img) {
                if (empty(trim($img['src'] ?? $img['image_url'] ?? ''))) {
                    $errors[] = "\"{$screenTitle}\": Image Carousel image #".($i + 1).' has no source.';
                }
            }
        }

        if (in_array($type, ['radio', 'checkbox', 'select', 'chips'], true)) {
            $options = $field['options'] ?? [];
            if ($options === []) {
                $errors[] = "\"{$screenTitle}\": \"{$field['label']}\" has no options.";
            }
            if ($type === 'chips' && count($options) > 0 && count($options) < 2) {
                $errors[] = "\"{$screenTitle}\": ChipsSelector requires at least 2 options.";
            }
            if ($type === 'chips' && count($options) > 20) {
                $errors[] = "\"{$screenTitle}\": ChipsSelector allows max 20 options.";
            }
        }

        if ($type === 'navigation_list' && empty($field['list_items'] ?? [])) {
            $errors[] = "\"{$screenTitle}\": NavigationList requires at least one list item.";
        }

        if ($type === 'if_condition') {
            $then = $field['then_children'] ?? [];
            if ($then === []) {
                $errors[] = "\"{$screenTitle}\": If component \"then\" branch cannot be empty.";
            }
            $thenHasFooter = collect($then)->contains(fn ($c) => in_array($c['type'] ?? '', self::FOOTER_TYPES, true));
            $elseHasFooter = collect($field['else_children'] ?? [])->contains(fn ($c) => in_array($c['type'] ?? '', self::FOOTER_TYPES, true));
            if ($thenHasFooter xor $elseHasFooter) {
                $errors[] = "\"{$screenTitle}\": If component with Footer must have Footer in both then and else branches.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function validateRichTextRules(array $fields, int $screenIndex): void
    {
        if ($fields === []) {
            return;
        }

        $richTextCount = 0;
        $otherComponentCount = 0;
        $otherTypes = [];

        foreach ($fields as $field) {
            $t = $field['type'] ?? 'unknown';
            if ($t === 'richtext') {
                $richTextCount++;
            } elseif (! in_array($t, self::FOOTER_TYPES, true)) {
                $otherComponentCount++;
                $otherTypes[] = $t;
            }
        }

        if ($richTextCount === 0) {
            return;
        }

        if ($richTextCount > 1) {
            throw new \Exception("Screen {$screenIndex}: RichText can only appear once per screen");
        }

        if ($otherComponentCount > 0) {
            throw new \Exception(
                "Screen {$screenIndex}: RichText can only be paired with Footer. Incompatible: ".implode(', ', $otherTypes)
            );
        }
    }

    private function richTextValue(array $field): string|array
    {
        $text = $field['placeholder'] ?: ($field['label'] ?? '');
        if ($field['richtext_as_array'] ?? false) {
            return array_values(array_filter(preg_split('/\r\n|\r|\n/', $text) ?: []));
        }

        return $text;
    }

    private function buildTextInput(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'TextInput',
            'name' => $this->getComponentName($field) ?? 'text_'.$fieldId,
            'label' => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
            'input-type' => $field['input_type'] ?? 'text',
        ];

        if (! empty($field['helper_text'])) {
            $component['helper-text'] = $field['helper_text'];
        }

        if (! empty($field['pattern'])) {
            $component['pattern'] = $field['pattern'];
        }

        if (isset($field['min_chars'])) {
            $component['min-chars'] = (string) $field['min_chars'];
        }

        if (isset($field['max_chars'])) {
            $component['max-chars'] = (string) $field['max_chars'];
        }

        if (! empty($field['sensitive'])) {
            $component['sensitive'] = true;
        }

        return $component;
    }

    private function buildTextArea(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'TextArea',
            'name' => $this->getComponentName($field) ?? 'textarea_'.$fieldId,
            'label' => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
            'max-length' => (int) ($field['max_length'] ?? 600),
        ];

        if (! empty($field['helper_text'])) {
            $component['helper-text'] = $field['helper_text'];
        }

        return $component;
    }

    private function buildSelectionGroup(array $field, int|string $fieldId, string $metaType): array
    {
        $prefix = match ($metaType) {
            'RadioButtonsGroup' => 'radio_',
            'CheckboxGroup' => 'checkbox_',
            default => 'select_',
        };

        $component = [
            'type' => $metaType,
            'name' => $this->getComponentName($field) ?? $prefix.$fieldId,
            'label' => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
            'data-source' => $this->resolveDataSource($field),
        ];

        if (! empty($field['description'])) {
            $component['description'] = $field['description'];
        }

        if ($metaType === 'CheckboxGroup') {
            if (isset($field['min_selected'])) {
                $component['min-selected-items'] = (int) $field['min_selected'];
            }
            if (isset($field['max_selected'])) {
                $component['max-selected-items'] = (int) $field['max_selected'];
            }
        }

        $this->applyDynamicBindings($component, $field);
        $this->applyOnSelectAction($component, $field);

        return $component;
    }

    private function buildDatePicker(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'DatePicker',
            'name' => $this->getComponentName($field) ?? 'date_'.$fieldId,
            'label' => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
        ];

        $this->applyDynamicBindings($component, $field);
        $this->applyOnSelectAction($component, $field);

        return $component;
    }

    private function buildChipsSelector(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'ChipsSelector',
            'name' => $this->getComponentName($field) ?? 'chips_'.$fieldId,
            'label' => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
            'data-source' => $this->resolveDataSource($field),
        ];

        if (! empty($field['description'])) {
            $component['description'] = $field['description'];
        }

        if (isset($field['min_selected'])) {
            $component['min-selected-items'] = (int) $field['min_selected'];
        }

        if (isset($field['max_selected'])) {
            $component['max-selected-items'] = (int) $field['max_selected'];
        }

        $this->applyDynamicBindings($component, $field);
        $this->applyOnSelectAction($component, $field);

        return $component;
    }

    private function buildCalendarPicker(array $field, int|string $fieldId): array
    {
        $mode = $field['calendar_mode'] ?? 'single';

        $component = [
            'type' => 'CalendarPicker',
            'name' => $this->getComponentName($field) ?? 'calendar_'.$fieldId,
            'mode' => $mode,
        ];

        if ($mode === 'range') {
            $component['label'] = [
                'start-date' => $field['label_start'] ?? $field['label'] ?? 'Start date',
                'end-date' => $field['label_end'] ?? 'End date',
            ];
            $component['required'] = [
                'start-date' => (bool) ($field['required_start'] ?? $field['required'] ?? false),
                'end-date' => (bool) ($field['required_end'] ?? $field['required'] ?? false),
            ];

            if (! empty($field['helper_text']) || ! empty($field['helper_text_start']) || ! empty($field['helper_text_end'])) {
                $component['helper-text'] = [
                    'start-date' => $field['helper_text_start'] ?? $field['helper_text'] ?? '',
                    'end-date' => $field['helper_text_end'] ?? $field['helper_text'] ?? '',
                ];
            }
        } else {
            $component['label'] = $field['label'] ?? '';
            $component['required'] = $field['required'] ?? false;

            if (! empty($field['helper_text'])) {
                $component['helper-text'] = $field['helper_text'];
            }
        }

        if (! empty($field['min_date'])) {
            $component['min-date'] = $field['min_date'];
        }

        if (! empty($field['max_date'])) {
            $component['max-date'] = $field['max_date'];
        }

        $this->applyDynamicBindings($component, $field);
        $this->applyOnSelectAction($component, $field);

        return $component;
    }

    private function buildOptIn(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'OptIn',
            'name' => 'optin_'.$fieldId,
            'label' => $field['label'] ?? '',
            'required' => $field['required'] ?? false,
        ];

        if (! empty($field['read_more_url'])) {
            $component['on-click-action'] = [
                'name' => 'open_url',
                'url' => $field['read_more_url'],
            ];
        }

        return $component;
    }

    private function buildPhotoPicker(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'PhotoPicker',
            'name' => 'photo_'.$fieldId,
            'label' => $field['label'] ?? 'Upload photo',
            'photo-source' => $field['photo_source'] ?? 'camera_gallery',
            'min-uploaded-photos' => (int) ($field['required'] ?? false ? max(1, (int) ($field['min_uploaded'] ?? 1)) : (int) ($field['min_uploaded'] ?? 0)),
            'max-uploaded-photos' => (int) ($field['max_uploaded'] ?? 1),
        ];

        if (! empty($field['description'])) {
            $component['description'] = $field['description'];
        }

        return $component;
    }

    private function buildDocumentPicker(array $field, int|string $fieldId): array
    {
        $component = [
            'type' => 'DocumentPicker',
            'name' => 'document_'.$fieldId,
            'label' => $field['label'] ?? 'Upload document',
            'min-uploaded-documents' => (int) ($field['required'] ?? false ? max(1, (int) ($field['min_uploaded'] ?? 1)) : (int) ($field['min_uploaded'] ?? 0)),
            'max-uploaded-documents' => (int) ($field['max_uploaded'] ?? 1),
        ];

        if (! empty($field['description'])) {
            $component['description'] = $field['description'];
        }

        if (! empty($field['allowed_mime_types'])) {
            $component['allowed-mime-types'] = $field['allowed_mime_types'];
        }

        return $component;
    }

    private function buildFooter(array $field, ?string $nextScreenId, bool $isTerminal): array
    {
        $targetScreen = $field['navigate_next'] ?? $nextScreenId ?? 'NEXT_SCREEN';
        $actionName = $field['on_click_action'] ?? ($isTerminal ? 'complete' : 'navigate');
        $payload = $field['on_click_payload'] ?? [];

        if ($isTerminal || $actionName === 'complete') {
            $clickAction = [
                'name' => 'complete',
                'payload' => is_array($payload) && $payload !== [] ? $payload : new \stdClass(),
            ];
        } elseif ($actionName === 'navigate') {
            $clickAction = [
                'name' => 'navigate',
                'next' => ['type' => 'screen', 'name' => $targetScreen],
                'payload' => is_array($payload) && $payload !== [] ? $payload : new \stdClass(),
            ];
        } else {
            $clickAction = [
                'name' => $actionName,
                'payload' => is_array($payload) && $payload !== [] ? $payload : new \stdClass(),
            ];
        }

        return [
            'type' => 'Footer',
            'label' => $field['label'] ?? ($isTerminal ? 'Submit' : 'Continue'),
            'on-click-action' => $this->normalizeClickAction($clickAction),
        ];
    }

    /**
     * @param  array<string, mixed>  $action
     * @return array<string, mixed>
     */
    public function normalizeClickAction(array $action): array
    {
        $name = (string) ($action['name'] ?? 'navigate');

        if (in_array($name, ['data_exchange', 'complete', 'open_url', 'update_data'], true)) {
            unset($action['next']);
        }

        if ($name === 'navigate' && empty($action['next'])) {
            unset($action['next']);
        }

        return $action;
    }

    /**
     * @param  array<int, string>  $currentScreenDynamicKeys
     */
    private function shouldUseDataExchangeFooter(bool $usesEndpointFlow, array $currentScreenDynamicKeys): bool
    {
        return $usesEndpointFlow || $currentScreenDynamicKeys !== [];
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<int, string>  $currentScreenDynamicKeys
     */
    private function resolveFooterClickActionName(
        array $field,
        bool $isTerminal,
        bool $usesEndpointFlow,
        array $currentScreenDynamicKeys,
        string $defaultAction
    ): string {
        if ($isTerminal) {
            return 'complete';
        }

        $requested = (string) ($field['on_click_action'] ?? $defaultAction);

        if ($requested === 'complete') {
            return 'complete';
        }

        if ($this->shouldUseDataExchangeFooter($usesEndpointFlow, $currentScreenDynamicKeys)) {
            return 'data_exchange';
        }

        return $requested === 'data_exchange' ? 'data_exchange' : 'navigate';
    }

    private function buildNavigationList(array $field, int|string $fieldId): array
    {
        $items = array_map(function ($item) {
            $entry = [
                'id' => (string) ($item['id'] ?? $item['value'] ?? ''),
                'main-content' => [
                    'title' => $item['title'] ?? $item['label'] ?? 'Option',
                ],
            ];

            if (! empty($item['description'])) {
                $entry['main-content']['description'] = $item['description'];
            }

            if (! empty($item['next_screen_id'])) {
                $itemPayload = $item['on_click_payload'] ?? [];
                $entry['on-click-action'] = [
                    'name' => $item['on_click_action'] ?? 'navigate',
                    'next' => ['type' => 'screen', 'name' => $item['next_screen_id']],
                    'payload' => is_array($itemPayload) && $itemPayload !== [] ? $itemPayload : new \stdClass(),
                ];
            }

            return $entry;
        }, $field['list_items'] ?? []);

        $component = [
            'type' => 'NavigationList',
            'name' => 'navlist_'.$fieldId,
            'list-items' => $items,
        ];

        if (! empty($field['label'])) {
            $component['label'] = $field['label'];
        }

        if (! empty($field['description'])) {
            $component['description'] = $field['description'];
        }

        return $component;
    }

    /**
     * @param  callable(string): string  $resolveImageSrc
     */
    private function buildIfComponent(
        array $field,
        ?string $nextScreenId,
        bool $isTerminal,
        callable $resolveImageSrc
    ): array {
        $component = [
            'type' => 'If',
            'condition' => $field['condition'] ?? '${true}',
            'then' => $this->convertBranchChildren($field['then_children'] ?? [], $nextScreenId, $isTerminal, $resolveImageSrc),
        ];

        $elseChildren = $this->convertBranchChildren($field['else_children'] ?? [], $nextScreenId, $isTerminal, $resolveImageSrc);
        if ($elseChildren !== []) {
            $component['else'] = $elseChildren;
        }

        return $component;
    }

    /**
     * @param  callable(string): string  $resolveImageSrc
     */
    private function buildSwitchComponent(
        array $field,
        ?string $nextScreenId,
        bool $isTerminal,
        callable $resolveImageSrc
    ): array {
        $cases = [];
        foreach ($field['cases'] ?? [] as $case) {
            $key = (string) ($case['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $children = $this->convertBranchChildren($case['children'] ?? [], $nextScreenId, $isTerminal, $resolveImageSrc);
            if ($children !== []) {
                $cases[$key] = $children;
            }
        }

        return [
            'type' => 'Switch',
            'value' => $field['switch_value'] ?? '${data.value}',
            'cases' => $cases,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<int, array<string, mixed>>
     */
    private function convertBranchChildren(
        array $children,
        ?string $nextScreenId,
        bool $isTerminal,
        callable $resolveImageSrc
    ): array {
        return array_values(array_filter(array_map(
            fn ($child) => $this->convertFieldToComponent($child, $nextScreenId, $isTerminal, $resolveImageSrc),
            $children
        )));
    }

    /**
     * @return array<int, array<string, string>>|string
     */
    private function resolveDataSource(array $field): array|string
    {
        if (! empty($field['dynamic_data_source']) && ! empty($field['data_source_key'])) {
            return '${data.'.$field['data_source_key'].'}';
        }

        return $this->convertOptionsToDataSource($field['options'] ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, string>>
     */
    private function convertOptionsToDataSource(array $options): array
    {
        return array_map(fn ($option) => [
            'id' => (string) ($option['value'] ?? $option['id'] ?? ''),
            'title' => $option['label'] ?? '',
        ], $options);
    }

    private function applyDynamicBindings(array &$component, array $field): void
    {
        if (! empty($field['visible_binding'])) {
            $component['visible'] = $field['visible_binding'];
        }

        if (! empty($field['required_binding'])) {
            $component['required'] = $field['required_binding'];
        }
    }

    private function applyOnSelectAction(array &$component, array $field): void
    {
        $action = $field['on_select_action'] ?? null;
        if (! in_array($action, ['data_exchange', 'update_data'], true)) {
            return;
        }

        $payload = $field['on_select_payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $name = $component['name'] ?? null;
        if ($name && $payload === [] && ! array_key_exists($name, $payload)) {
            $payload[$name] = '${form.'.$name.'}';
        }

        $component['on-select-action'] = [
            'name' => $action,
            'payload' => empty($payload) ? new \stdClass() : $payload,
        ];
    }
}
