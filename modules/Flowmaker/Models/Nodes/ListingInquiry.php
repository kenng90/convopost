<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\ListCatalog;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogUrlService;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Wpbox\Models\Message;

class ListingInquiry extends Node
{
    private const INQUIRY_RESUMED_STATE = 'listing_inquiry_resumed';

    public function listenForReply($message, $data)
    {
        $extraData = is_object($data) ? ($data->extra ?? null) : ($data['extra'] ?? null);
        $messageText = is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '');
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);

        if (! $contact) {
            return;
        }

        if ($this->messageLooksLikeListingInquiry($messageText)) {
            if ($this->hasInquiryAlreadyResumed($contact)) {
                Log::info('Listing Inquiry: inquiry already resumed', ['nodeId' => $this->id]);

                return;
            }

            $this->advanceAfterInquiry($message, $data, $contact);

            return;
        }

        if ($extraData === null || $extraData === '') {
            Log::info('Listing Inquiry: waiting for customer inquiry');

            return;
        }

        $settings = $this->getDataAsArray()['settings'] ?? [];
        $catalogId = $settings['catalogId'] ?? null;
        if (! $catalogId) {
            return;
        }

        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
        if (! $catalog) {
            return;
        }

        $itemId = $this->resolveItemIdFromExtra((string) $extraData);
        $selectedItem = $this->findItemInCatalog($catalog->items ?? [], $itemId);

        $contact->clearContactState($this->flow_id, 'current_node');

        if ($selectedItem) {
            $contact->setContactState($this->flow_id, 'selected_listing', json_encode($selectedItem));
            $nextNode = $this->resolveInquiryNextNode();
            if ($nextNode) {
                $nextNode->process($message, $data);
            }
        } else {
            $elseNode = $this->getNextNodeId('else');
            if ($elseNode) {
                $elseNode->process($message, $data);
            }
        }
    }

    public function process($message, $data)
    {
        if ($this->isStartNode) {
            $messageText = is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '');

            if ($this->messageLooksLikeListingInquiry($messageText)) {
                $this->listenForReply($message, $data);

                return ['success' => true];
            }

            return $this->sendListingCatalog($message, $data);
        }

        return $this->sendListingCatalog($message, $data);
    }

    private function sendListingCatalog($message, $data)
    {
        $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
        $contact = Contact::find($contactId);
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $catalogId = $settings['catalogId'] ?? null;

        if (! $contact || ! $catalogId) {
            return ['success' => false];
        }

        $catalog = ListCatalog::withoutGlobalScopes()->find($catalogId);
        if (! $catalog || $catalog->isCommerce()) {
            Log::error('Listing Inquiry: catalog missing or not a listing catalog', ['catalogId' => $catalogId]);

            return ['success' => false];
        }

        $contact->clearContactState($this->flow_id, self::INQUIRY_RESUMED_STATE);
        $contact->setContactState($this->flow_id, 'catalog_id', $catalogId);

        try {
            $header = $contact->changeVariables($settings['header'] ?? 'Browse our listings', $this->flow_id);
            $footer = $contact->changeVariables(
                $settings['footer'] ?? 'Tap the link to view listings and inquire on WhatsApp.',
                $this->flow_id
            );

            $flowCallback = app(CatalogFlowCallbackService::class);
            $catalogUrl = app(CatalogUrlService::class)->publicUrl(
                $catalog,
                null,
                $flowCallback->buildQueryParams($this->flow_id, $contact->id, (string) $this->id, (int) $catalogId)
            );

            $messageText = "{$header}\n\n{$catalogUrl}\n\n{$footer}";

            $messageToBeSend = Message::create([
                'contact_id' => $contact->id,
                'company_id' => $contact->company_id,
                'value' => $messageText,
                'is_message_by_contact' => false,
                'is_campign_messages' => false,
                'status' => 1,
                'fb_message_id' => null,
            ]);
            $messageToBeSend->save();
            $contact->sendMessageToWhatsApp($messageToBeSend, $contact);
            $contact->setContactState($this->flow_id, 'current_node', $this->id);
        } catch (\Exception $e) {
            Log::error('Listing Inquiry: failed to send catalog link', ['error' => $e->getMessage()]);

            return ['success' => false];
        }

        return ['success' => true];
    }

    private function advanceAfterInquiry($message, $data, Contact $contact): void
    {
        $contact->setContactState($this->flow_id, self::INQUIRY_RESUMED_STATE, '1');
        $contact->clearContactState($this->flow_id, 'current_node');

        $nextNode = $this->resolveInquiryNextNode();
        if ($nextNode) {
            $nextNode->process($message, $data);
        }
    }

    private function hasInquiryAlreadyResumed(Contact $contact): bool
    {
        return $contact->getContactStateValue($this->flow_id, self::INQUIRY_RESUMED_STATE) === '1';
    }

    private function messageLooksLikeListingInquiry(?string $message): bool
    {
        if ($message === null || $message === '') {
            return false;
        }

        return str_contains($message, 'Inquiry from');
    }

    protected function resolveInquiryNextNode(): ?Node
    {
        foreach (['onListingInquiry', 'onInquiry'] as $handle) {
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

        return $this->getNextNodeId('else');
    }

    private function resolveItemIdFromExtra(string $extra): string
    {
        if (app(CatalogFlowCallbackService::class)->isListingInquiryExtra($extra)) {
            return app(CatalogFlowCallbackService::class)->itemIdFromListingInquiryExtra($extra);
        }

        if (str_starts_with($extra, 'listing:')) {
            return substr($extra, 8);
        }

        return $extra;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function findItemInCatalog(array $items, string $itemId): ?array
    {
        foreach ($items as $item) {
            if (($item['id'] ?? null) === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
