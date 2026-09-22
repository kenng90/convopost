<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\Crypt;

class CatalogFlowCallbackService
{
    public const CHECKOUT_COMPLETE_EXTRA = 'catalog_checkout_complete';

    public const LISTING_INQUIRY_PREFIX = 'listing:';

    public function listingInquiryExtra(string $itemId): string
    {
        return self::LISTING_INQUIRY_PREFIX.$itemId;
    }

    public function isListingInquiryExtra(?string $extra): bool
    {
        return is_string($extra) && str_starts_with($extra, self::LISTING_INQUIRY_PREFIX);
    }

    public function itemIdFromListingInquiryExtra(string $extra): string
    {
        return substr($extra, strlen(self::LISTING_INQUIRY_PREFIX));
    }

    /**
     * @return array{flow_id: int, contact_id: int, node_id: string, catalog_id: int}
     */
    public function decodeToken(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        foreach (['flow_id', 'contact_id', 'node_id', 'catalog_id'] as $key) {
            if (! isset($payload[$key])) {
                return null;
            }
        }

        if ((int) $payload['catalog_id'] <= 0 || (int) $payload['flow_id'] <= 0 || (int) $payload['contact_id'] <= 0) {
            return null;
        }

        return [
            'flow_id' => (int) $payload['flow_id'],
            'contact_id' => (int) $payload['contact_id'],
            'node_id' => (string) $payload['node_id'],
            'catalog_id' => (int) $payload['catalog_id'],
        ];
    }

    public function makeToken(int $flowId, int $contactId, string $nodeId, int $catalogId): string
    {
        return Crypt::encryptString(json_encode([
            'flow_id' => $flowId,
            'contact_id' => $contactId,
            'node_id' => $nodeId,
            'catalog_id' => $catalogId,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     */
    public function buildQueryParams(int $flowId, int $contactId, string $nodeId, int $catalogId): array
    {
        return [
            'flow_token' => $this->makeToken($flowId, $contactId, $nodeId, $catalogId),
        ];
    }
}
