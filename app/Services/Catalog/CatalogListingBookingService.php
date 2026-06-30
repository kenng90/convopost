<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;

class CatalogListingBookingService
{
    public const BOOKING_MARKER = 'Booking request from';

    public const INQUIRY_MARKER = 'Inquiry from';

    public function __construct(
        protected CatalogListingInquiryService $catalogListingInquiryService,
        protected CatalogCurrencyService $catalogCurrencyService,
        protected CatalogTemplateRegistry $catalogTemplateRegistry,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null
     * }  $details
     */
    public function buildBookingMessage(ListCatalog $catalog, array $item, array $details): string
    {
        $presentation = $this->catalogTemplateRegistry->presentationForCatalog($catalog);
        $lines = [];

        $lines[] = '📅 *'.self::BOOKING_MARKER.' '.$catalog->name.'*';
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
            $value = $this->catalogListingInquiryService->fieldValue($item, $fieldKey);
            if ($value !== null && $value !== '') {
                $label = $this->fieldLabel($presentation, $fieldKey);
                $lines[] = '• '.$label.': '.$value;
            }
        }

        if (! empty($details['customerName'])) {
            $lines[] = '';
            $lines[] = '👤 *Customer:* '.$details['customerName'];
        }

        if (! empty($details['customerPhone'])) {
            $lines[] = '📱 *Phone:* '.$details['customerPhone'];
        }

        if (! empty($details['preferredDateTime'])) {
            $lines[] = '🗓 *Preferred date/time:* '.$details['preferredDateTime'];
        }

        if (! empty($details['notes'])) {
            $lines[] = '';
            $lines[] = '📝 *Notes:* '.$details['notes'];
        }

        $lines[] = '';
        $lines[] = 'Sent via '.$catalog->name;

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function buildWhatsAppUrl(Company $company, ListCatalog $catalog, array $item, string $message): ?string
    {
        $phone = app(CatalogWhatsAppOrderService::class)->resolveNumber($company);
        if (! $phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    public function messageLooksLikeBookingRequest(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        return str_contains($message, self::BOOKING_MARKER);
    }

    public function messageLooksLikeInquiry(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        return str_contains($message, self::INQUIRY_MARKER);
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
