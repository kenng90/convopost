<?php

namespace App\Services\Flowmaker;

use App\Models\WhatsappFlow;
use App\Services\WhatsappFlowSubmissionService;
use Illuminate\Support\Collection;
use Modules\Contacts\Models\Field;

class WhatsappFormFieldMapper
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'name' => ['name', 'full_name', 'fullname', 'customer_name', 'first_name'],
        'email' => ['email', 'e_mail', 'email_address', 'mail'],
        'phone' => ['phone', 'mobile', 'telephone', 'phone_number', 'whatsapp'],
        'city' => ['city', 'town', 'location'],
        'amount' => ['amount', 'budget', 'price', 'deposit', 'total', 'payment'],
        'company' => ['company', 'business', 'organisation', 'organization'],
    ];

    public function __construct(
        private WhatsappFlowSubmissionService $submissionService
    ) {
    }

    /**
     * Suggest CRM field mappings from form field labels/keys.
     *
     * @return list<array{id: string, formFieldKey: string, contactFieldId: string}>
     */
    public function suggestCrmMappings(WhatsappFlow $form, int $companyId): array
    {
        $formFields = $this->submissionService->getFieldOptionsForForm($form);
        if ($formFields === []) {
            return [];
        }

        /** @var Collection<int, Field> $crmFields */
        $crmFields = Field::query()
            ->where('company_id', $companyId)
            ->get();

        if ($crmFields->isEmpty()) {
            return [];
        }

        $usedCrmIds = [];
        $mappings = [];

        foreach ($formFields as $formField) {
            $match = $this->matchCrmField($formField, $crmFields, $usedCrmIds);
            if (! $match) {
                continue;
            }

            $usedCrmIds[] = (int) $match->id;
            $mappings[] = [
                'id' => 'map_'.substr(md5($formField['key'].'_'.$match->id), 0, 8),
                'formFieldKey' => $formField['key'],
                'contactFieldId' => (string) $match->id,
            ];
        }

        return $mappings;
    }

    /**
     * Build starter conditions from the first select/radio field with options.
     *
     * @return list<array{id: string, fieldName: string, operator: string, value: string}>
     */
    public function suggestLeadConditions(WhatsappFlow $form): array
    {
        $definitions = app(\App\Services\WhatsappFlowResponseService::class)
            ->getInputFieldDefinitions($form);

        foreach ($definitions as $definition) {
            if (! in_array($definition['type'] ?? '', ['select', 'radio', 'chips'], true)) {
                continue;
            }

            $options = $definition['options'] ?? [];
            if (! is_array($options) || count($options) < 2) {
                continue;
            }

            $conditions = [];
            foreach (array_slice($options, 0, 3) as $index => $option) {
                $value = is_array($option)
                    ? (string) ($option['id'] ?? $option['title'] ?? $option['value'] ?? '')
                    : (string) $option;

                if ($value === '') {
                    continue;
                }

                $conditions[] = [
                    'id' => 'cond_'.($index + 1),
                    'fieldName' => $definition['key'],
                    'operator' => '==',
                    'value' => $value,
                ];
            }

            return $conditions;
        }

        return [];
    }

    /**
     * Find a numeric/currency-like form field key for checkout amount binding.
     */
    public function suggestAmountFieldKey(WhatsappFlow $form): ?string
    {
        $fields = $this->submissionService->getFieldOptionsForForm($form);

        foreach ($fields as $field) {
            $normalized = $this->normalize($field['key'].' '.$field['label']);
            foreach (self::ALIASES['amount'] as $alias) {
                if (str_contains($normalized, $alias)) {
                    return $field['key'];
                }
            }
        }

        foreach ($fields as $field) {
            if (in_array($field['type'] ?? '', ['text', 'textarea'], true)) {
                $normalized = $this->normalize($field['label']);
                if (str_contains($normalized, 'amount') || str_contains($normalized, 'budget') || str_contains($normalized, 'price')) {
                    return $field['key'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array{key: string, label: string, type: string}  $formField
     * @param  Collection<int, Field>  $crmFields
     * @param  list<int>  $usedCrmIds
     */
    private function matchCrmField(array $formField, Collection $crmFields, array $usedCrmIds): ?Field
    {
        $formNorm = $this->normalize($formField['key'].' '.$formField['label']);

        foreach ($crmFields as $crmField) {
            if (in_array((int) $crmField->id, $usedCrmIds, true)) {
                continue;
            }

            $crmNorm = $this->normalize((string) $crmField->name);
            if ($crmNorm !== '' && ($crmNorm === $formNorm || str_contains($formNorm, $crmNorm) || str_contains($crmNorm, $formNorm))) {
                return $crmField;
            }

            foreach (self::ALIASES as $aliases) {
                $formHits = false;
                $crmHits = false;
                foreach ($aliases as $alias) {
                    if (str_contains($formNorm, $alias)) {
                        $formHits = true;
                    }
                    if (str_contains($crmNorm, $alias)) {
                        $crmHits = true;
                    }
                }
                if ($formHits && $crmHits) {
                    return $crmField;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $value) ?? '', '_'));
    }
}
