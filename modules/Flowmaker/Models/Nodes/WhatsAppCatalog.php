<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\Company;
use App\Models\ListCatalog;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogUrlService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Wpbox\Models\Message;

class WhatsAppCatalog extends Node
{
    public function listenForReply($message, $data)
    {
        Log::info('WhatsApp Catalog: listening for product selection', ['nodeId' => $this->id]);

        $extraData = $data->extra;
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        $settings = $this->getDataAsArray()['settings'] ?? [];

        if ($extraData == null || $extraData == '') {
            Log::info('WhatsApp Catalog: no product selected');

            return;
        }

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

            $contact->setContactState($this->flow_id, 'selected_product', json_encode($selectedProduct));

            $nextNode = $this->getNextNodeId('onProductSelected');
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
            $extraData = $data->extra ?? null;

            if (! empty($extraData)) {
                Log::info('WhatsApp Catalog: resuming after product selection', ['extraData' => $extraData]);
                $this->listenForReply($message, $data);
            } else {
                Log::info('WhatsApp Catalog: sending catalog for first time');

                return $this->sendCatalog($message, $data);
            }

            return ['success' => true];
        }

        return $this->sendCatalog($message, $data);
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

        $contact->setContactState($this->flow_id, 'catalog_id', $catalogId);
        $contact->setContactState($this->flow_id, 'catalog_items', json_encode($catalog->items ?? []));

        $displayMode = $settings['displayMode'] ?? 'link';
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
        if (preg_match('/^catalog_(.+)_id[^_]+_flow\d+$/', $extraData, $matches)) {
            return $matches[1];
        }

        return $extraData;
    }

    protected function getNextNodeId($handleId = null)
    {
        foreach ($this->outgoingEdges as $edge) {
            $sourceHandle = $edge->getSourceHandle() ?? '';
            if ($handleId === null || str_contains($sourceHandle, $handleId)) {
                return $edge->getTarget();
            }
        }

        return null;
    }
}
