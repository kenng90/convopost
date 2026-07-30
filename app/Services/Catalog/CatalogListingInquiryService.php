<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;

class CatalogListingInquiryService
{
    public function __construct(
        protected CatalogCurrencyService $catalogCurrencyService,
        protected CatalogTemplateRegistry $catalogTemplateRegistry,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function buildMessage(ListCatalog $catalog, array $item, ?string $customerName = null, ?string $notes = null): string
    {
        $presentation = $this->catalogTemplateRegistry->presentationForCatalog($catalog);

        if (($presentation['vertical'] ?? '') === 'jobs') {
            return $this->buildJobApplicationMessage($catalog, $item, $customerName, $notes);
        }

        $lines = [];

        $lines[] = '🔔 *Inquiry from '.$catalog->name.'*';
        $lines[] = '';
        $lines[] = '📌 *'.$this->lineValue($item['title'] ?? 'Listing').'*';

        if (! empty($item['id'])) {
            $lines[] = 'Ref: '.$item['id'];
        }

        if (isset($item['price']) && (float) $item['price'] > 0) {
            $lines[] = '💰 '.$this->catalogCurrencyService->formatAmount(
                $catalog->company,
                (float) $item['price']
            );
        }

        foreach ($presentation['card_highlights'] as $fieldKey) {
            $value = $this->fieldValue($item, $fieldKey);
            if ($value !== null && $value !== '') {
                $label = $this->fieldLabel($presentation, $fieldKey);
                $lines[] = '• '.$label.': '.$value;
            }
        }

        if ($customerName) {
            $lines[] = '';
            $lines[] = '👤 '.$customerName;
        }

        if ($notes) {
            $lines[] = '';
            $lines[] = '📝 '.$notes;
        }

        $lines[] = '';
        $lines[] = 'Sent via '.$catalog->name;

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function buildJobApplicationMessage(ListCatalog $catalog, array $item, ?string $customerName = null, ?string $notes = null): string
    {
        $presentation = $this->catalogTemplateRegistry->presentationForCatalog($catalog);
        $lines = [];

        $lines[] = '📋 *Job application — '.$catalog->name.'*';
        $lines[] = '';
        $lines[] = '💼 *'.$this->lineValue($item['title'] ?? 'Role').'*';

        if (! empty($item['id'])) {
            $lines[] = 'Ref: '.$item['id'];
        }

        foreach (['company', 'employment_type', 'location', 'salary', 'experience', 'education', 'deadline'] as $fieldKey) {
            $value = $this->fieldValue($item, $fieldKey);
            if ($value !== null && $value !== '') {
                $label = $this->fieldLabel($presentation, $fieldKey);
                $lines[] = '• '.$label.': '.$value;
            }
        }

        $skills = $this->fieldValue($item, 'skills');
        if ($skills !== null && $skills !== '') {
            $lines[] = '';
            $lines[] = '🛠 *Skills*';
            $lines[] = $skills;
        }

        $lines[] = '';
        $lines[] = 'Hi, I would like to apply for this role.';

        if ($customerName) {
            $lines[] = '';
            $lines[] = '👤 '.$customerName;
        }

        if ($notes) {
            $lines[] = '';
            $lines[] = '📝 '.$notes;
        }

        $lines[] = '';
        $lines[] = 'Sent via '.$catalog->name;

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function buildMailtoApplyUrl(array $item): ?string
    {
        $email = $this->fieldValue($item, 'apply_email');
        if ($email === null || $email === '') {
            return null;
        }

        $jobTitle = trim((string) ($item['title'] ?? 'Role'));
        $ref = trim((string) ($item['id'] ?? ''));
        $subject = 'Application: '.$jobTitle;
        $body = "Hi,\n\nI would like to apply for the {$jobTitle} position";
        if ($ref !== '') {
            $body .= " (Ref: {$ref})";
        }
        $body .= ".\n\nPlease find my CV attached.\n\nBest regards,\n";

        return 'mailto:'.rawurlencode($email)
            .'?subject='.rawurlencode($subject)
            .'&body='.rawurlencode($body);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function buildWhatsAppUrl(Company $company, ListCatalog $catalog, array $item, ?string $customerName = null, ?string $notes = null): ?string
    {
        $phone = app(CatalogWhatsAppOrderService::class)->resolveNumber($company);
        if (! $phone) {
            return null;
        }

        $message = $this->buildMessage($catalog, $item, $customerName, $notes);
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function fieldValue(array $item, string $fieldKey): ?string
    {
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

        if (array_key_exists($fieldKey, $metadata) && $metadata[$fieldKey] !== null && $metadata[$fieldKey] !== '') {
            return (string) $metadata[$fieldKey];
        }

        if (array_key_exists($fieldKey, $item) && $item[$fieldKey] !== null && $item[$fieldKey] !== '') {
            return (string) $item[$fieldKey];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $presentation
     */
    private function fieldLabel(array $presentation, string $fieldKey): string
    {
        foreach ($presentation['item_fields'] as $field) {
            if (($field['key'] ?? '') === $fieldKey) {
                return (string) ($field['label'] ?? ucfirst(str_replace('_', ' ', $fieldKey)));
            }
        }

        return ucfirst(str_replace('_', ' ', $fieldKey));
    }

    private function lineValue(string $value): string
    {
        return trim($value) !== '' ? $value : 'Listing';
    }
}
