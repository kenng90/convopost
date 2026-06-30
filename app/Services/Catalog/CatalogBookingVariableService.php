<?php

namespace App\Services\Catalog;

use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class CatalogBookingVariableService
{
    public const DEFAULT_PREFIX = 'listing_booking';

    public const LEGACY_SELECTED_LISTING_KEY = 'selected_listing';

    public function resolvePrefix(?string $prefix): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $prefix);

        return $clean !== '' ? $clean : self::DEFAULT_PREFIX;
    }

    public function resolvePrefixFromFlowNode(Flow $flow, string $nodeId): string
    {
        return app(CatalogFlowNodeSettingsService::class)->resolvePrefixFromFlowNode($flow, $nodeId);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null,
     *     completionType?: string|null,
     *     reservationId?: int|null
     * }  $details
     * @return array<string, string>
     */
    public function buildVariableMap(
        string $prefix,
        array $item,
        array $details,
        ?string $bookingMessage = null
    ): array {
        $payload = [
            'item_id' => (string) ($item['id'] ?? ''),
            'item_title' => (string) ($item['title'] ?? ''),
            'customer_name' => (string) ($details['customerName'] ?? ''),
            'customer_phone' => (string) ($details['customerPhone'] ?? ''),
            'preferred_datetime' => (string) ($details['preferredDateTime'] ?? ''),
            'notes' => (string) ($details['notes'] ?? ''),
            'completion_type' => (string) ($details['completionType'] ?? 'booking'),
            'reservation_id' => isset($details['reservationId']) ? (int) $details['reservationId'] : null,
        ];

        $variables = [
            $prefix.'_item_id' => $payload['item_id'],
            $prefix.'_item_title' => $payload['item_title'],
            $prefix.'_customer_name' => $payload['customer_name'],
            $prefix.'_customer_phone' => $payload['customer_phone'],
            $prefix.'_preferred_datetime' => $payload['preferred_datetime'],
            $prefix.'_notes' => $payload['notes'],
            $prefix.'_message' => $bookingMessage ?? '',
            $prefix.'_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            self::LEGACY_SELECTED_LISTING_KEY => json_encode($item, JSON_THROW_ON_ERROR),
        ];

        if (! empty($details['reservationId'])) {
            $variables[$prefix.'_reservation_id'] = (string) $details['reservationId'];
        }

        return $variables;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null,
     *     completionType?: string|null,
     *     reservationId?: int|null
     * }  $details
     */
    public function storeOnContact(
        Contact $contact,
        int $flowId,
        string $prefix,
        array $item,
        array $details,
        ?string $bookingMessage = null
    ): void {
        $variables = $this->buildVariableMap($prefix, $item, $details, $bookingMessage);

        foreach ($variables as $name => $value) {
            $contact->setContactState($flowId, $name, $value);
        }
    }
}
