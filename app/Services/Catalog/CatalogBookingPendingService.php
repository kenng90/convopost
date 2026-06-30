<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Flowmaker\Jobs\ResumeFlowFromListingInquiry;

class CatalogBookingPendingService
{
    public const PENDING_FLAG = 'catalog_booking_pending';

    public const PENDING_MESSAGE = 'catalog_booking_pending_message';

    public const PENDING_ITEM_ID = 'catalog_booking_pending_item_id';

    public const PENDING_COMPLETION_TYPE = 'catalog_booking_completion_type';

    public const PENDING_CATALOG_ID = 'catalog_booking_pending_catalog_id';

    public const PENDING_NODE_ID = 'catalog_booking_pending_node_id';

    public const PENDING_PAYLOAD = 'catalog_booking_pending_payload';

    public function __construct(
        protected CatalogBookingVariableService $bookingVariableService,
        protected CatalogListingBookingService $catalogListingBookingService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null,
     *     completionType?: string|null
     * }  $details
     */
    public function storePending(
        Contact $contact,
        int $flowId,
        string $nodeId,
        ListCatalog $catalog,
        array $item,
        array $details,
        string $bookingMessage
    ): void {
        $flow = Flow::withoutGlobalScopes()->find($flowId);
        $prefix = $flow
            ? $this->bookingVariableService->resolvePrefixFromFlowNode($flow, $nodeId)
            : CatalogBookingVariableService::DEFAULT_PREFIX;

        $details['completionType'] = $details['completionType'] ?? 'booking';

        $this->bookingVariableService->storeOnContact(
            $contact,
            $flowId,
            $prefix,
            $item,
            $details,
            $bookingMessage
        );

        $contact->setContactState($flowId, self::PENDING_FLAG, '1');
        $contact->setContactState($flowId, self::PENDING_MESSAGE, $bookingMessage);
        $contact->setContactState($flowId, self::PENDING_ITEM_ID, (string) ($item['id'] ?? ''));
        $contact->setContactState($flowId, self::PENDING_COMPLETION_TYPE, (string) $details['completionType']);
        $contact->setContactState($flowId, self::PENDING_CATALOG_ID, (string) $catalog->id);
        $contact->setContactState($flowId, self::PENDING_NODE_ID, $nodeId);
        $contact->setContactState($flowId, self::PENDING_PAYLOAD, json_encode($details, JSON_THROW_ON_ERROR));

        $this->maybeAutoResumeFlow($contact, $flowId, $nodeId, (string) ($item['id'] ?? ''));
    }

    private function maybeAutoResumeFlow(Contact $contact, int $flowId, string $nodeId, string $itemId): void
    {
        if ($itemId === '') {
            return;
        }

        $flow = Flow::withoutGlobalScopes()->find($flowId);
        if (! $flow) {
            return;
        }

        $flowData = json_decode($flow->flow_data ?: $flow->draft_flow_data ?: '{}', true);
        $nodes = $flowData['nodes'] ?? [];

        foreach ($nodes as $node) {
            if (($node['id'] ?? '') !== $nodeId) {
                continue;
            }

            $autoResume = ! empty($node['data']['settings']['autoResumeFlow']);
            if ($autoResume) {
                ResumeFlowFromListingInquiry::dispatch($flowId, $contact->id, $itemId)->onQueue('flows');
            }

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null,
     *     completionType?: string|null
     * }  $details
     */
    public function storePendingFromFlowToken(
        ?string $flowToken,
        int $catalogId,
        array $item,
        array $details,
        string $bookingMessage
    ): void {
        if (! $flowToken) {
            return;
        }

        $context = app(CatalogFlowCallbackService::class)->decodeToken($flowToken);
        if (! $context || (int) $context['catalog_id'] !== $catalogId) {
            return;
        }

        $contact = Contact::withoutGlobalScopes()->find($context['contact_id']);
        $catalog = ListCatalog::withoutGlobalScope(CompanyScope::class)->find($catalogId);

        if (! $contact || ! $catalog) {
            return;
        }

        $this->storePending(
            $contact,
            (int) $context['flow_id'],
            (string) $context['node_id'],
            $catalog,
            $item,
            $details,
            $bookingMessage
        );
    }

    public function hasPending(Contact $contact, int $flowId): bool
    {
        return $contact->getContactStateValue($flowId, self::PENDING_FLAG) === '1';
    }

    public function isConfirmationMessage(Contact $contact, int $flowId, ?string $messageText): bool
    {
        if ($messageText === null || $messageText === '') {
            return false;
        }

        if ($this->catalogListingBookingService->messageLooksLikeBookingRequest($messageText)
            || $this->catalogListingBookingService->messageLooksLikeInquiry($messageText)) {
            return true;
        }

        if (! $this->hasPending($contact, $flowId)) {
            return false;
        }

        $pendingMessage = $contact->getContactStateValue($flowId, self::PENDING_MESSAGE);
        if ($pendingMessage === '') {
            return false;
        }

        return $this->normalizeMessage($messageText) === $this->normalizeMessage($pendingMessage);
    }

    /**
     * @return array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null,
     *     completionType?: string|null
     * }|null
     */
    public function pendingPayload(Contact $contact, int $flowId): ?array
    {
        $raw = $contact->getContactStateValue($flowId, self::PENDING_PAYLOAD);
        if ($raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function clearPending(Contact $contact, int $flowId): void
    {
        foreach ([
            self::PENDING_FLAG,
            self::PENDING_MESSAGE,
            self::PENDING_ITEM_ID,
            self::PENDING_COMPLETION_TYPE,
            self::PENDING_CATALOG_ID,
            self::PENDING_NODE_ID,
            self::PENDING_PAYLOAD,
        ] as $state) {
            $contact->clearContactState($flowId, $state);
        }
    }

    private function normalizeMessage(string $message): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($message));

        return $collapsed ?? trim($message);
    }
}
