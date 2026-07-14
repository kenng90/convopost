<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Catalog\CatalogCheckoutPendingService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Flowmaker\FlowRunLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Wpbox\Models\Message;

class WhatsAppCatalog extends Node
{
    private const CHECKOUT_RESUMED_STATE = 'catalog_checkout_resumed';

    public function listenForReply($message, $data)
    {
        Log::info('WhatsApp Catalog: listening for product selection', ['nodeId' => $this->id]);

        $extraData = is_object($data) ? ($data->extra ?? null) : ($data['extra'] ?? null);
        $messageText = is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '');
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        if ($this->isCheckoutCompleteSignal((string) $extraData)) {
            $this->advanceAfterCheckout($message, $data, $contact);

            return;
        }

        if (($extraData === null || $extraData === '') && app(CatalogCheckoutPendingService::class)->isOrderConfirmationMessage($contact, $this->flow_id, $messageText)) {
            if ($this->hasCheckoutAlreadyResumed($contact)) {
                Log::info('WhatsApp Catalog: order message ignored, checkout already resumed', ['nodeId' => $this->id]);

                return;
            }

            $this->advanceAfterCheckout($message, $data, $contact);

            return;
        }

        if ($extraData == null || $extraData == '') {
            Log::info('WhatsApp Catalog: no product selected');

            return;
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $catalogId = $settings['catalogId'] ?? null;
        if (! $catalogId) {
            Log::error('WhatsApp Catalog: no catalog configured');

            return;
        }

        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
        if (! $catalog) {
            Log::error('WhatsApp Catalog: catalog not found', ['catalogId' => $catalogId]);

            return;
        }

        $productId = $this->resolveProductIdFromExtra((string) $extraData);
        $selectedProduct = null;

        foreach ($catalog->items ?? [] as $item) {
            if (($item['id'] ?? null) === $productId) {
                $selectedProduct = $item;
                break;
            }
        }

        $contact->clearContactState($this->flow_id, 'current_node');

        if ($selectedProduct) {
            Log::info('WhatsApp Catalog: product selected', [
                'productId' => $selectedProduct['id'],
                'productTitle' => $selectedProduct['title'],
                'nodeId' => $this->id,
            ]);
            FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_product_selected', (string) $this->id, (string) ($selectedProduct['id'] ?? ''));

            $contact->setContactState($this->flow_id, 'selected_product', json_encode($selectedProduct));

            $nextNode = $this->resolveCheckoutNextNode();
            if ($nextNode) {
                $nextNode->process($message, $data);
            }
        } else {
            Log::info('WhatsApp Catalog: product not found in catalog', ['productId' => $productId]);
            $elseNode = $this->getNextNodeId('else');
            if ($elseNode) {
                $elseNode->process($message, $data);
            }
        }
    }

    public function process($message, $data)
    {
        Log::info('WhatsApp Catalog: processing', ['isStartNode' => $this->isStartNode, 'nodeId' => $this->id]);

        if ($this->isStartNode) {
            $extraData = is_object($data) ? ($data->extra ?? null) : ($data['extra'] ?? null);
            $messageText = is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '');
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            $pendingService = app(CatalogCheckoutPendingService::class);

            if ($this->isCheckoutCompleteSignal((string) $extraData)
                || (! empty($extraData))
                || ($contact && $pendingService->isOrderConfirmationMessage($contact, $this->flow_id, $messageText))) {
                Log::info('WhatsApp Catalog: resuming after selection or checkout', ['extraData' => $extraData]);
                $this->listenForReply($message, $data);
            } else {
                Log::info('WhatsApp Catalog: sending catalog for first time');

                return $this->sendCatalog($message, $data);
            }

            return ['success' => true];
        }

        return $this->sendCatalog($message, $data);
    }

    private function advanceAfterCheckout($message, $data, Contact $contact): void
    {
        if ($this->hasCheckoutAlreadyResumed($contact)) {
            Log::info('WhatsApp Catalog: checkout already resumed', ['nodeId' => $this->id]);

            return;
        }

        Log::info('WhatsApp Catalog: advancing after checkout', ['nodeId' => $this->id]);

        app(CatalogCheckoutPendingService::class)->clearPending($contact, $this->flow_id);
        $contact->setContactState($this->flow_id, self::CHECKOUT_RESUMED_STATE, '1');
        $contact->clearContactState($this->flow_id, 'current_node');
        FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_checkout_completed', (string) $this->id);

        $nextNode = $this->resolveCheckoutNextNode();
        if ($nextNode) {
            $nextNode->process($message, $data);
        } else {
            Log::warning('WhatsApp Catalog: no next node after checkout', ['nodeId' => $this->id]);
        }
    }

    private function hasCheckoutAlreadyResumed(Contact $contact): bool
    {
        return $contact->getContactStateValue($this->flow_id, self::CHECKOUT_RESUMED_STATE) === '1';
    }

    private function isCheckoutCompleteSignal(string $extraData): bool
    {
        return $extraData === CatalogFlowCallbackService::CHECKOUT_COMPLETE_EXTRA;
    }

    /**
     * Prefer explicit checkout handles, then generic/default edges (not "else").
     */
    protected function resolveCheckoutNextNode(): ?Node
    {
        foreach (['onProductSelected', 'onCheckoutComplete'] as $handle) {
            $nextNode = $this->getNextNodeId($handle);
            if ($nextNode) {
                return $nextNode;
            }
        }

        foreach ($this->outgoingEdges as $edge) {
            $handle = $edge->getSourceHandle() ?? '';
            if ($handle === '' && $edge->getTarget()) {
                return $edge->getTarget();
            }
        }

        foreach ($this->outgoingEdges as $edge) {
            $handle = $edge->getSourceHandle() ?? '';
            if ($handle !== 'else' && $edge->getTarget()) {
                return $edge->getTarget();
            }
        }

        return $this->getNextNodeId('else');
    }

    private function sendCatalog($message, $data)
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $catalogId = $settings['catalogId'] ?? null;

        if (! $catalogId) {
            Log::error('WhatsApp Catalog: no catalog configured');

            return ['success' => false];
        }

        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
        if (! $catalog) {
            Log::error('WhatsApp Catalog: catalog not found', ['catalogId' => $catalogId]);

            return ['success' => false];
        }

        $contact->clearContactState($this->flow_id, self::CHECKOUT_RESUMED_STATE);
        app(CatalogCheckoutPendingService::class)->clearPending($contact, $this->flow_id);
        $contact->setContactState($this->flow_id, 'catalog_id', $catalogId);
        $contact->setContactState($this->flow_id, 'catalog_items', json_encode($catalog->items ?? []));

        $displayMode = $settings['displayMode'] ?? 'interactive_list';
        $items = $catalog->items ?? [];

        if ($displayMode === 'interactive_list' && count($items) > 0 && count($items) <= 10) {
            return $this->sendInteractiveList($contact, $catalog, $settings);
        }

        try {
            $header = $contact->changeVariables($settings['header'] ?? 'Browse our products', $this->flow_id);
            $footer = $contact->changeVariables($settings['footer'] ?? 'Tap the link to browse and checkout. Your order will continue in this chat.', $this->flow_id);

            $flowCallback = app(CatalogFlowCallbackService::class);
            $catalogUrl = app(CatalogUrlService::class)->publicUrl(
                $catalog,
                null,
                $flowCallback->buildQueryParams($this->flow_id, $contact->id, (string) $this->id, (int) $catalogId)
            );

            $messageText = "{$header}\n\n{$catalogUrl}\n\n{$footer}";

            $messageData = [
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => $messageText,
                'is_message_by_contact' => false,
                'is_campign_messages' => false,
                'status' => 1,
                'fb_message_id' => null,
            ];

            $messageToBeSend = Message::create($messageData);
            $messageToBeSend->save();
            $contact->sendMessageToWhatsApp($messageToBeSend, $contact);

            $contact->setContactState($this->flow_id, 'current_node', $this->id);

            FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_link_sent', (string) $this->id, (string) $catalogId);

            Log::info('WhatsApp Catalog: link message sent', [
                'catalogId' => $catalogId,
                'url' => $catalogUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp Catalog: failed to send catalog', ['error' => $e->getMessage()]);

            return ['success' => false];
        }

        return ['success' => true];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function sendInteractiveList(Contact $contact, ListCatalog $catalog, array $settings): array
    {
        $company = Company::find($contact->company_id);
        $token = $company?->getConfig('plain_token', '') ?? '';
        $header = $contact->changeVariables($settings['header'] ?? 'Choose a product', $this->flow_id);
        $footer = $contact->changeVariables($settings['footer'] ?? 'Select an item from the list below', $this->flow_id);
        $buttonText = $contact->changeVariables($settings['buttonText'] ?? 'View products', $this->flow_id);

        $rows = [];
        foreach (array_slice($catalog->items ?? [], 0, 10) as $item) {
            $productId = (string) ($item['id'] ?? '');
            if ($productId === '') {
                continue;
            }

            $price = isset($item['price']) ? ' — '.$item['price'] : '';
            $rows[] = [
                'id' => $this->listRowId($productId),
                'title' => mb_substr((string) ($item['title'] ?? 'Product'), 0, 24),
                'description' => mb_substr(((string) ($item['description'] ?? '')).$price, 0, 72),
            ];
        }

        if ($rows === []) {
            return ['success' => false];
        }

        $payload = [
            'token' => $token,
            'phone' => $contact->phone,
            'message' => $header,
            'header' => $catalog->name,
            'footer' => $footer,
            'action' => [
                'button' => $buttonText,
                'sections' => [[
                    'title' => $catalog->name,
                    'rows' => $rows,
                ]],
            ],
        ];

        $contact->setContactState($this->flow_id, 'current_node', $this->id);
        FlowRunLogger::log((int) $this->flow_id, (int) $contact->id, 'catalog_link_sent', (string) $this->id, (string) $catalog->id);

        try {
            $response = Http::post(config('app.url').'/api/wpbox/sendlistmessage', $payload);
            if (! $response->successful()) {
                Log::error('WhatsApp Catalog: interactive list failed', ['body' => $response->body()]);

                return ['success' => false];
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Catalog: interactive list exception', ['error' => $e->getMessage()]);

            return ['success' => false];
        }

        return ['success' => true];
    }

    private function listRowId(string $productId): string
    {
        return 'catalog_'.$productId.'_id'.$this->id.'_flow'.$this->flow_id;
    }

    private function resolveProductIdFromExtra(string $extraData): string
    {
        $suffix = '_id'.$this->id.'_flow'.$this->flow_id;
        if (str_starts_with($extraData, 'catalog_') && str_ends_with($extraData, $suffix)) {
            return substr($extraData, strlen('catalog_'), -strlen($suffix));
        }

        return $extraData;
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            $sourceHandle = $edge->getSourceHandle() ?? '';
            if ($handleId === null || str_contains($sourceHandle, (string) $handleId)) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
