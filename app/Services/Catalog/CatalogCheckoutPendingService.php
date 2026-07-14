<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;
use App\Scopes\CompanyScope;
use Modules\Flowmaker\Jobs\ResumeFlowFromCatalogCheckout;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;

class CatalogCheckoutPendingService
{
    public const PENDING_FLAG = 'catalog_checkout_pending';

    public const PENDING_MESSAGE = 'catalog_pending_message';

    public const PENDING_CART = 'catalog_pending_cart';

    public const PENDING_CATALOG_ID = 'catalog_pending_catalog_id';

    public const PENDING_NODE_ID = 'catalog_pending_node_id';

    public function __construct(
        protected CatalogCheckoutVariableService $checkoutVariableService,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     */
    public function storePending(
        Contact $contact,
        int $flowId,
        string $nodeId,
        ListCatalog $catalog,
        array $cartItems,
        ?string $orderMessage = null
    ): void {
        if ($cartItems === []) {
            return;
        }

        $flow = Flow::withoutGlobalScopes()->find($flowId);
        $prefix = $flow
            ? $this->checkoutVariableService->resolvePrefixFromFlowNode($flow, $nodeId)
            : CatalogCheckoutVariableService::DEFAULT_PREFIX;

        $this->checkoutVariableService->storeOnContact(
            $contact,
            $flowId,
            $prefix,
            $catalog,
            $cartItems,
            $orderMessage
        );

        $contact->setContactState($flowId, self::PENDING_FLAG, '1');
        $contact->setContactState($flowId, self::PENDING_MESSAGE, $orderMessage ?? '');
        $contact->setContactState($flowId, self::PENDING_CART, json_encode($cartItems, JSON_THROW_ON_ERROR));
        $contact->setContactState($flowId, self::PENDING_CATALOG_ID, (string) $catalog->id);
        $contact->setContactState($flowId, self::PENDING_NODE_ID, $nodeId);

        $this->maybeAutoResumeFlow($contact, $flowId, $nodeId, $catalog->id, $cartItems, $orderMessage);
    }

    /**
     * @param  list<array<string, mixed>>  $cartItems
     */
    private function maybeAutoResumeFlow(
        Contact $contact,
        int $flowId,
        string $nodeId,
        int $catalogId,
        array $cartItems,
        ?string $orderMessage
    ): void {
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

            if (! empty($node['data']['settings']['autoResumeFlow'])) {
                $productId = (string) ($cartItems[0]['id'] ?? '');
                ResumeFlowFromCatalogCheckout::dispatch(
                    $flowId,
                    $contact->id,
                    $productId,
                    $cartItems,
                    $nodeId,
                    $orderMessage,
                    $catalogId
                )->onQueue('flows');
            }

            return;
        }
    }

    public function storePendingFromFlowToken(
        ?string $flowToken,
        int $catalogId,
        array $cartItems,
        ?string $orderMessage = null
    ): void {
        if (! $flowToken || $cartItems === []) {
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
            $cartItems,
            $orderMessage
        );
    }

    public function hasPending(Contact $contact, int $flowId): bool
    {
        return $contact->getContactStateValue($flowId, self::PENDING_FLAG) === '1';
    }

    public function isOrderConfirmationMessage(Contact $contact, int $flowId, ?string $messageText): bool
    {
        if ($messageText === null || $messageText === '') {
            return false;
        }

        if ($this->messageLooksLikeCatalogOrder($messageText)) {
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

    public function clearPending(Contact $contact, int $flowId): void
    {
        foreach ([
            self::PENDING_FLAG,
            self::PENDING_MESSAGE,
            self::PENDING_CART,
            self::PENDING_CATALOG_ID,
            self::PENDING_NODE_ID,
        ] as $state) {
            $contact->clearContactState($flowId, $state);
        }
    }

    public function messageLooksLikeCatalogOrder(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        if (str_contains($message, 'New Order from Catalog:')) {
            return true;
        }

        return str_contains($message, '*Items:*') && str_contains($message, '*Total:*');
    }

    private function normalizeMessage(string $message): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($message));

        return $collapsed ?? trim($message);
    }
}
