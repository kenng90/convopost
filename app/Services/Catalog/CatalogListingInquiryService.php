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
