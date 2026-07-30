<?php

namespace App\Services;

use App\Models\WhatsappFlow;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;

class WhatsappFlowVariableMapper
{
    public function __construct(
        private WhatsappFlowResponseService $responseService
    ) {
    }

    public function resolvePrefix(array $settings, string $nodeId): string
    {
        $prefix = trim((string) ($settings['variablePrefix'] ?? ''));
        if ($prefix !== '') {
            $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $prefix) ?? '';

            return trim($sanitized, '_') ?: $this->defaultPrefixFromNodeId($nodeId);
        }

        return $this->defaultPrefixFromNodeId($nodeId);
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @param  list<array{fieldKey?: string, workflowVar?: string}>  $customMappings
     */
    public function syncToContactState(
        Contact $contact,
        int $automationFlowId,
        array $responseData,
        ?WhatsappFlow $whatsappFlow,
        string $prefix,
        array $customMappings = [],
        bool $keepLegacy = true
    ): void {
        $definitions = $this->responseService->getInputFieldDefinitions($whatsappFlow);
        $definitionMap = collect($definitions)->keyBy('key');
        $flattened = $this->flattenNestedValues($responseData);

        $contact->setContactState($automationFlowId, $prefix.'_responses', json_encode($responseData));

        foreach ($flattened as $fieldKey => $value) {
            $stringValue = is_array($value) ? json_encode($value) : (string) $value;
            $contact->setContactState($automationFlowId, $prefix.'_'.$fieldKey, $stringValue);

            $definition = $definitionMap->get(explode('.', $fieldKey)[0]);
            if ($definition && ! empty($definition['label'])) {
                $slugKey = $this->slugifyFieldLabel($definition['label']);
                if ($slugKey !== '') {
                    $contact->setContactState($automationFlowId, $prefix.'_'.$slugKey, $stringValue);
                }
            }
        }

        foreach ($customMappings as $mapping) {
            $fieldKey = $mapping['fieldKey'] ?? null;
            $workflowVar = $mapping['workflowVar'] ?? null;
            if (! $fieldKey || ! $workflowVar) {
                continue;
            }

            $sanitizedVar = preg_replace('/[^a-zA-Z0-9_]/', '_', $workflowVar) ?? '';
            if ($sanitizedVar === '') {
                continue;
            }

            $value = $this->resolveFieldValue($flattened, (string) $fieldKey);
            if ($value === null) {
                continue;
            }

            $contact->setContactState(
                $automationFlowId,
                $sanitizedVar,
                is_array($value) ? json_encode($value) : (string) $value
            );
        }

        if ($keepLegacy) {
            $contact->setContactState($automationFlowId, 'whatsapp_flow_responses', json_encode($responseData));

            foreach ($flattened as $fieldKey => $value) {
                $stringValue = is_array($value) ? json_encode($value) : (string) $value;
                $contact->setContactState($automationFlowId, 'form_'.$fieldKey, $stringValue);

                $definition = $definitionMap->get(explode('.', $fieldKey)[0]);
                if ($definition && ! empty($definition['label'])) {
                    $slugKey = $this->slugifyFieldLabel($definition['label']);
                    if ($slugKey !== '') {
                        $contact->setContactState($automationFlowId, 'form_'.$slugKey, $stringValue);
                    }
                }
            }
        }

        Log::info('WhatsApp Form: synced prefixed variables to contact state', [
            'contact_id' => $contact->id,
            'flow_id' => $automationFlowId,
            'prefix' => $prefix,
            'field_count' => count($flattened),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function flattenNestedValues(array $data, ?int $maxDepth = null): array
    {
        $maxDepth ??= (int) config('whatsapp-flows.nested_variable_max_depth', 3);
        $flat = [];

        foreach ($data as $key => $value) {
            $this->flattenValue($flat, (string) $key, $value, 0, $maxDepth);
        }

        return $flat;
    }

    /**
     * @return list<array{key: string, label: string, value: string, category: string}>
     */
    public function buildVariableCatalog(string $prefix, array $formFields, bool $includeLegacy = true): array
    {
        $vars = [
            [
                'key' => $prefix.'_responses',
                'label' => 'All responses (JSON)',
                'value' => $prefix.'_responses',
                'category' => 'WhatsApp Form',
            ],
        ];

        foreach ($formFields as $field) {
            $fieldKey = $field['key'] ?? null;
            if (! $fieldKey) {
                continue;
            }

            $vars[] = [
                'key' => $prefix.'_'.$fieldKey,
                'label' => ($field['label'] ?? $fieldKey).' ('.$prefix.')',
                'value' => $prefix.'_'.$fieldKey,
                'category' => 'WhatsApp Form',
            ];
        }

        if ($includeLegacy) {
            $vars[] = [
                'key' => 'whatsapp_flow_responses',
                'label' => 'All responses (legacy)',
                'value' => 'whatsapp_flow_responses',
                'category' => 'WhatsApp Form',
            ];
        }

        return $vars;
    }

    /**
     * @param  array<string, mixed>  $flat
     */
    private function flattenValue(array &$flat, string $prefix, mixed $value, int $depth, int $maxDepth): void
    {
        if ($value === null) {
            $flat[$prefix] = '';

            return;
        }

        if (! is_array($value)) {
            $flat[$prefix] = $value;

            return;
        }

        if ($depth >= $maxDepth || $this->isListArray($value)) {
            $flat[$prefix] = $value;

            return;
        }

        $hasStringKeys = collect(array_keys($value))->contains(fn ($k) => is_string($k));
        if (! $hasStringKeys) {
            $flat[$prefix] = $value;

            return;
        }

        foreach ($value as $childKey => $childValue) {
            $this->flattenValue($flat, $prefix.'.'.$childKey, $childValue, $depth + 1, $maxDepth);
        }
    }

    /**
     * @param  array<string, mixed>  $flat
     */
    private function resolveFieldValue(array $flat, string $fieldKey): mixed
    {
        if (array_key_exists($fieldKey, $flat)) {
            return $flat[$fieldKey];
        }

        foreach ($flat as $key => $value) {
            if (str_starts_with($key, $fieldKey.'.')) {
                return $value;
            }
        }

        return null;
    }

    private function isListArray(array $value): bool
    {
        if ($value === []) {
            return true;
        }

        return array_keys($value) === range(0, count($value) - 1);
    }

    private function defaultPrefixFromNodeId(string $nodeId): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '_', $nodeId) ?? 'form';

        return trim($sanitized, '_') ?: 'form';
    }

    private function slugifyFieldLabel(string $label): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? '', '_'));

        return trim($slug, '_');
    }
}
