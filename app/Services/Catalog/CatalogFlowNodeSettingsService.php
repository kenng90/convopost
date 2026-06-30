<?php

namespace App\Services\Catalog;

use Modules\Flowmaker\Models\Flow;

class CatalogFlowNodeSettingsService
{
    public const DEFAULT_COMPLETION_TYPE = 'booking';

    public const DEFAULT_BOOKING_PREFIX = 'listing_booking';

    public const DEFAULT_BOOKING_BACKEND = 'whatsapp_only';

    /**
     * @return array{
     *     completionType: string,
     *     bookingVariablePrefix: string,
     *     requirePreferredDateTime: bool,
     *     bookingBackend: string
     * }
     */
    public function resolveListingNodeSettings(?string $flowToken): array
    {
        $defaults = $this->defaultListingNodeSettings();

        if (! $flowToken) {
            return $defaults;
        }

        $context = app(CatalogFlowCallbackService::class)->decodeToken($flowToken);
        if (! $context) {
            return $defaults;
        }

        $flow = Flow::withoutGlobalScopes()->find($context['flow_id']);
        if (! $flow) {
            return $defaults;
        }

        $flowData = json_decode($flow->flow_data ?? '{}', true);
        if (! is_array($flowData)) {
            return $defaults;
        }

        foreach ($flowData['nodes'] ?? [] as $node) {
            if (! is_array($node) || ($node['id'] ?? '') !== $context['node_id']) {
                continue;
            }

            if (($node['type'] ?? '') !== 'listing_inquiry') {
                return $defaults;
            }

            $settings = $node['data']['settings'] ?? [];

            return [
                'completionType' => $this->normalizeCompletionType($settings['completionType'] ?? $defaults['completionType']),
                'bookingVariablePrefix' => $this->sanitizePrefix($settings['bookingVariablePrefix'] ?? $defaults['bookingVariablePrefix']),
                'requirePreferredDateTime' => (bool) ($settings['requirePreferredDateTime'] ?? $defaults['requirePreferredDateTime']),
                'bookingBackend' => $this->normalizeBookingBackend($settings['bookingBackend'] ?? $defaults['bookingBackend']),
            ];
        }

        return $defaults;
    }

    /**
     * @return array{
     *     completionType: string,
     *     bookingVariablePrefix: string,
     *     requirePreferredDateTime: bool,
     *     bookingBackend: string
     * }
     */
    public function defaultListingNodeSettings(): array
    {
        return [
            'completionType' => self::DEFAULT_COMPLETION_TYPE,
            'bookingVariablePrefix' => self::DEFAULT_BOOKING_PREFIX,
            'requirePreferredDateTime' => false,
            'bookingBackend' => self::DEFAULT_BOOKING_BACKEND,
        ];
    }

    public function resolvePrefixFromFlowNode(Flow $flow, string $nodeId): string
    {
        $flowData = json_decode($flow->flow_data ?? '{}', true);
        if (! is_array($flowData)) {
            return self::DEFAULT_BOOKING_PREFIX;
        }

        foreach ($flowData['nodes'] ?? [] as $node) {
            if (! is_array($node) || ($node['id'] ?? '') !== $nodeId) {
                continue;
            }

            $settings = $node['data']['settings'] ?? [];

            return $this->sanitizePrefix($settings['bookingVariablePrefix'] ?? self::DEFAULT_BOOKING_PREFIX);
        }

        return self::DEFAULT_BOOKING_PREFIX;
    }

    public function resolveBookingBackendFromFlowNode(Flow $flow, string $nodeId): string
    {
        $flowData = json_decode($flow->flow_data ?? '{}', true);
        if (! is_array($flowData)) {
            return self::DEFAULT_BOOKING_BACKEND;
        }

        foreach ($flowData['nodes'] ?? [] as $node) {
            if (! is_array($node) || ($node['id'] ?? '') !== $nodeId) {
                continue;
            }

            $settings = $node['data']['settings'] ?? [];

            return $this->normalizeBookingBackend($settings['bookingBackend'] ?? self::DEFAULT_BOOKING_BACKEND);
        }

        return self::DEFAULT_BOOKING_BACKEND;
    }

    private function normalizeCompletionType(string $value): string
    {
        return in_array($value, ['inquiry', 'booking'], true) ? $value : self::DEFAULT_COMPLETION_TYPE;
    }

    private function normalizeBookingBackend(string $value): string
    {
        return in_array($value, ['whatsapp_only', 'reminders'], true) ? $value : self::DEFAULT_BOOKING_BACKEND;
    }

    private function sanitizePrefix(?string $prefix): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $prefix);

        return $clean !== '' ? $clean : self::DEFAULT_BOOKING_PREFIX;
    }
}
