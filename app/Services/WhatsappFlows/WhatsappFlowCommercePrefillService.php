<?php

namespace App\Services\WhatsappFlows;

use App\Models\WhatsappFlow;
use Modules\Flowmaker\Models\Contact;

class WhatsappFlowCommercePrefillService
{
    /**
     * Build init/navigate payload data from contact automation state (catalog/listing context).
     *
     * @return array<string, mixed>
     */
    public function buildPrefillData(
        WhatsappFlow $form,
        ?Contact $contact = null,
        ?int $automationFlowId = null
    ): array {
        if (! $contact || ! $automationFlowId) {
            return [];
        }

        $candidates = [
            'product_title' => [
                'catalog_order_message',
                'listing_booking_item_title',
                'selected_product_title',
                'catalog_item_title',
            ],
            'product_id' => [
                'catalog_order_json',
                'listing_booking_reservation_id',
                'selected_product_id',
            ],
            'amount' => [
                'catalog_order_total_amount',
                'catalog_order_total',
                'listing_booking_amount',
            ],
            'customer_name' => [
                'listing_booking_customer_name',
                'customer_name',
            ],
            'notes' => [
                'listing_booking_notes',
                'catalog_order_items',
            ],
        ];

        $prefill = [];

        foreach ($candidates as $targetKey => $stateKeys) {
            foreach ($stateKeys as $stateKey) {
                $value = $contact->getContactStateValue($automationFlowId, $stateKey);
                if ($value === null || $value === '') {
                    continue;
                }

                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $prefill[$targetKey] = (string) $value;
                break;
            }
        }

        // Also expose raw known commerce vars under their own keys for dynamic_data screens.
        foreach ([
            'catalog_order_total_amount',
            'catalog_order_total',
            'catalog_order_item_count',
            'listing_booking_item_title',
            'listing_booking_preferred_datetime',
            'listing_booking_customer_name',
            'listing_booking_notes',
        ] as $key) {
            $value = $contact->getContactStateValue($automationFlowId, $key);
            if ($value === null || $value === '') {
                continue;
            }
            $prefill[$key] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return $this->filterToKnownScreenKeys($form, $prefill);
    }

    /**
     * Prefer keys that appear in screen dynamic_data / field names; keep useful extras.
     *
     * @param  array<string, mixed>  $prefill
     * @return array<string, mixed>
     */
    private function filterToKnownScreenKeys(WhatsappFlow $form, array $prefill): array
    {
        if ($prefill === []) {
            return [];
        }

        $knownKeys = [];
        foreach ($form->flow_json['screens'] ?? [] as $screen) {
            foreach ($screen['dynamic_data'] ?? [] as $entry) {
                if (is_array($entry) && ! empty($entry['key'])) {
                    $knownKeys[] = (string) $entry['key'];
                } elseif (is_string($entry)) {
                    $knownKeys[] = $entry;
                }
            }
            foreach ($screen['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }
                foreach (['name', 'meta_name', 'data_source_key'] as $attr) {
                    if (! empty($field[$attr])) {
                        $knownKeys[] = (string) $field[$attr];
                    }
                }
            }
        }

        if ($knownKeys === []) {
            return $prefill;
        }

        $known = array_fill_keys(array_unique($knownKeys), true);
        $filtered = [];
        foreach ($prefill as $key => $value) {
            if (isset($known[$key])) {
                $filtered[$key] = $value;
            }
        }

        // Always keep amount/product_title when present — screens often use body text, not keys.
        foreach (['amount', 'product_title', 'product_id', 'customer_name', 'notes'] as $fallback) {
            if (isset($prefill[$fallback]) && ! isset($filtered[$fallback])) {
                $filtered[$fallback] = $prefill[$fallback];
            }
        }

        return $filtered !== [] ? $filtered : $prefill;
    }
}
