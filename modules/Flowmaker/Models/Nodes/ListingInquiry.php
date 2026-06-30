<?php

namespace Modules\Flowmaker\Models\Nodes;

use App\Models\ListCatalog;
use App\Services\Catalog\CatalogBookingPendingService;
use App\Services\Catalog\CatalogBookingVariableService;
use App\Services\Catalog\CatalogFlowCallbackService;
use App\Services\Catalog\CatalogFlowNodeSettingsService;
use App\Services\Catalog\CatalogListingBookingReservationService;
use App\Services\Catalog\CatalogListingBookingService;
use App\Services\Catalog\CatalogUrlService;
use App\Services\Flowmaker\FlowRunLogger;
use Illuminate\Support\Facades\Log;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
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

        $pendingService = app(CatalogBookingPendingService::class);
        $bookingService = app(CatalogListingBookingService::class);

        if ($pendingService->isConfirmationMessage($contact, $this->flow_id, $messageText)
            || $bookingService->messageLooksLikeBookingRequest($messageText)
            || $bookingService->messageLooksLikeInquiry($messageText)) {
            if ($this->hasInquiryAlreadyResumed($contact)) {
                Log::info('Listing Inquiry: completion already resumed', ['nodeId' => $this->id]);

                return;
            }

            $this->advanceAfterCompletion($message, $data, $contact);

            return;
        }

        if ($extraData === null || $extraData === '') {
            Log::info('Listing Inquiry: waiting for customer booking or inquiry');

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
            foreach ($this->outgoingEdges as $edge) {
                if ($edge->getSourceHandle() === 'else' && $edge->getTarget()) {
                    $edge->getTarget()->process($message, $data);

                    return;
                }
            }
        }
    }

    public function process($message, $data)
    {
        if ($this->isStartNode) {
            $messageText = is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '');
            $contactId = is_object($data) ? $data->contact_id : $data['contact_id'];
            $contact = Contact::find($contactId);
            $pendingService = app(CatalogBookingPendingService::class);
            $bookingService = app(CatalogListingBookingService::class);

            if ($contact && (
                $pendingService->isConfirmationMessage($contact, $this->flow_id, $messageText)
                || $bookingService->messageLooksLikeBookingRequest($messageText)
                || $bookingService->messageLooksLikeInquiry($messageText)
            )) {
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
        app(CatalogBookingPendingService::class)->clearPending($contact, $this->flow_id);
        $contact->setContactState($this->flow_id, 'catalog_id', $catalogId);

        try {
            $header = $contact->changeVariables($settings['header'] ?? 'Browse our listings', $this->flow_id);
            $footer = $contact->changeVariables(
                $settings['footer'] ?? 'Tap the link to view listings and book on WhatsApp.',
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

    private function advanceAfterCompletion($message, $data, Contact $contact): void
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $catalogId = $settings['catalogId'] ?? null;
        $catalog = $catalogId ? ListCatalog::withoutGlobalScopes()->find($catalogId) : null;
        $pendingService = app(CatalogBookingPendingService::class);
        $payload = $pendingService->pendingPayload($contact, $this->flow_id) ?? [];
        $itemId = $pendingService->hasPending($contact, $this->flow_id)
            ? $contact->getContactStateValue($this->flow_id, CatalogBookingPendingService::PENDING_ITEM_ID)
            : '';

        if ($itemId === '' && $catalog) {
            $messageText = is_object($data) ? ($data->value ?? '') : ($data['value'] ?? '');
            $itemId = $this->guessItemIdFromMessage($catalog, $messageText) ?? '';
        }

        $selectedItem = $catalog && $itemId !== ''
            ? $this->findItemInCatalog($catalog->items ?? [], $itemId)
            : null;

        if ($selectedItem && $catalog) {
            $bookingBackend = $settings['bookingBackend']
                ?? app(CatalogFlowNodeSettingsService::class)->DEFAULT_BOOKING_BACKEND;

            $reservationId = app(CatalogListingBookingReservationService::class)->tryCreateReservation(
                $catalog->company,
                $selectedItem,
                $payload,
                (string) $bookingBackend
            );

            if ($reservationId) {
                $flow = Flow::withoutGlobalScopes()->find($this->flow_id);
                $prefix = $flow
                    ? app(CatalogBookingVariableService::class)->resolvePrefixFromFlowNode($flow, (string) $this->id)
                    : CatalogBookingVariableService::DEFAULT_PREFIX;

                $payload['reservationId'] = $reservationId;
                app(CatalogBookingVariableService::class)->storeOnContact(
                    $contact,
                    $this->flow_id,
                    $prefix,
                    $selectedItem,
                    $payload,
                    $contact->getContactStateValue($this->flow_id, CatalogBookingPendingService::PENDING_MESSAGE)
                );
            }

            $contact->setContactState($this->flow_id, 'selected_listing', json_encode($selectedItem));
        }

        $pendingService->clearPending($contact, $this->flow_id);
        $contact->setContactState($this->flow_id, self::INQUIRY_RESUMED_STATE, '1');
        $contact->clearContactState($this->flow_id, 'current_node');

        $completionType = (string) ($payload['completionType'] ?? $settings['completionType'] ?? 'booking');
        FlowRunLogger::log($this->flow_id, $contact->id, 'listing_inquiry_completed', $this->id, $completionType);

        $nextNode = $this->resolveInquiryNextNode($completionType);
        if ($nextNode) {
            $nextNode->process($message, $data);
        }
    }

    private function hasInquiryAlreadyResumed(Contact $contact): bool
    {
        return $contact->getContactStateValue($this->flow_id, self::INQUIRY_RESUMED_STATE) === '1';
    }

    protected function resolveInquiryNextNode(?string $completionType = null): ?Node
    {
        $settings = $this->getDataAsArray()['settings'] ?? [];
        $type = $completionType ?? (string) ($settings['completionType'] ?? 'booking');

        $preferredHandles = match ($type) {
            'inquiry' => ['onInquiry', 'onListingInquiry', 'onBooking'],
            'booking' => ['onBooking', 'onListingInquiry', 'onInquiry'],
            default => ['onListingInquiry', 'onBooking', 'onInquiry'],
        };

        foreach ($preferredHandles as $handle) {
            foreach ($this->outgoingEdges as $edge) {
                if ($edge->getSourceHandle() === $handle && $edge->getTarget()) {
                    return $edge->getTarget();
                }
            }
        }

        foreach ($this->outgoingEdges as $edge) {
            $handle = $edge->getSourceHandle() ?? '';
            if ($handle === '' && $edge->getTarget()) {
                return $edge->getTarget();
            }
        }

        foreach ($this->outgoingEdges as $edge) {
            if ($edge->getSourceHandle() === 'else' && $edge->getTarget()) {
                return $edge->getTarget();
            }
        }

        return null;
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

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function guessItemIdFromMessage(ListCatalog $catalog, string $message): ?string
    {
        if (! preg_match('/Ref:\s*(\S+)/u', $message, $matches)) {
            return null;
        }

        $ref = trim($matches[1]);
        $item = $this->findItemInCatalog($catalog->items ?? [], $ref);

        return $item ? $ref : null;
    }
}
