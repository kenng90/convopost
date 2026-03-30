<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\ListCatalog;
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

        // Get the selected product
        $catalogId = $settings['catalogId'] ?? null;
        if (!$catalogId) {
            Log::error('WhatsApp Catalog: no catalog configured');
            return;
        }

        $catalog = ListCatalog::find($catalogId);
        if (!$catalog) {
            Log::error('WhatsApp Catalog: catalog not found', ['catalogId' => $catalogId]);
            return;
        }

        // Find the selected product in the catalog
        $selectedProduct = null;
        foreach ($catalog->items as $item) {
            if ($item['id'] === $extraData) {
                $selectedProduct = $item;
                break;
            }
        }

        // Clear the waiting state
        $contact->clearContactState($this->flow_id, 'current_node');

        if ($selectedProduct) {
            Log::info('WhatsApp Catalog: product selected', [
                'productId' => $selectedProduct['id'],
                'productTitle' => $selectedProduct['title'],
                'nodeId' => $this->id
            ]);

            // Store selected product in contact state for invoice node to access
            $contact->setContactState($this->flow_id, 'selected_product', json_encode($selectedProduct));

            // Route to onProductSelected handle
            $nextNode = $this->getNextNodeId('onProductSelected');
            if ($nextNode) {
                $nextNode->process($message, $data);
            }
        } else {
            Log::info('WhatsApp Catalog: product not found in catalog', ['productId' => $extraData]);
            // Route to else handle if product not found
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
            // Check if we're resuming (user selected a product) by checking if extra data exists
            $extraData = $data->extra ?? null;

            if (!empty($extraData)) {
                // User has sent a product selection - resume and listen for reply
                Log::info('WhatsApp Catalog: resuming after product selection', ['extraData' => $extraData]);
                $this->listenForReply($message, $data);
            } else {
                // First time - send the catalog
                Log::info('WhatsApp Catalog: sending catalog for first time');
                return $this->sendCatalog($message, $data);
            }
            return ['success' => true];
        }

        return $this->sendCatalog($message, $data);
    }

    /**
     * Send the catalog message to the contact
     */
    private function sendCatalog($message, $data)
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        $settings = $this->getDataAsArray()['settings'] ?? [];

        $catalogId = $settings['catalogId'] ?? null;

        if (!$catalogId) {
            Log::error('WhatsApp Catalog: no catalog configured');
            return ['success' => false];
        }

        $catalog = ListCatalog::find($catalogId);
        if (!$catalog) {
            Log::error('WhatsApp Catalog: catalog not found', ['catalogId' => $catalogId]);
            return ['success' => false];
        }

        // Store catalog in contact state so it's available for the next node
        $contact->setContactState($this->flow_id, 'catalog_id', $catalogId);
        $contact->setContactState($this->flow_id, 'catalog_items', json_encode($catalog->items ?? []));

        try {
            // Build message with catalog link
            $header = $contact->changeVariables($settings['header'] ?? 'Browse our products', $this->flow_id);
            $catalogUrl = route('catalog.public', ['catalogId' => $catalogId]);
            $footer = $contact->changeVariables($settings['footer'] ?? 'Click the link above to view our catalog', $this->flow_id);

            // Create text message with catalog link
            $messageText = "{$header}\n\n{$catalogUrl}\n\n{$footer}";

            $messageData = [
                "contact_id" => $contact->id,
                "company_id" => $contact->company_id,
                "value" => $messageText,
                "is_message_by_contact" => false,
                "is_campign_messages" => false,
                "status" => 1,
                "fb_message_id" => null
            ];

            $messageToBeSend = Message::create($messageData);
            $messageToBeSend->save();

            // Send via WhatsApp
            $contact->sendMessageToWhatsApp($messageToBeSend, $contact);

            Log::info('WhatsApp Catalog: message sent successfully', [
                'catalogId' => $catalogId,
                'phone' => $contact->phone,
                'url' => $catalogUrl
            ]);

            // Set current node to wait for product selection
            $contact->setContactState($this->flow_id, 'current_node', $this->id);

        } catch (\Exception $e) {
            Log::error('WhatsApp Catalog: failed to send catalog', ['error' => $e->getMessage()]);
            return ['success' => false];
        }

        return ['success' => true];
    }

    /**
     * Get the next node by handle ID
     */
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
