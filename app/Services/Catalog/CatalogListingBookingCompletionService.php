<?php

namespace App\Services\Catalog;

use App\Models\ListCatalog;
use Modules\Flowmaker\Jobs\ResumeFlowFromListingInquiry;
use Modules\Flowmaker\Models\Contact;
use Modules\Flowmaker\Models\Flow;
use Modules\Reminders\Models\Reservation;

class CatalogListingBookingCompletionService
{
    public function __construct(
        protected CatalogFlowCallbackService $flowCallbackService,
        protected CatalogBookingVariableService $bookingVariableService,
        protected CatalogBookingPendingService $bookingPendingService,
        protected CatalogListingBookingService $catalogListingBookingService,
    ) {
    }

    /**
     * @return array{flow_id: int, contact_id: int, node_id: string, catalog_id: int}|null
     */
    public function flowContextFromToken(?string $flowToken): ?array
    {
        if (! $flowToken) {
            return null;
        }

        return $this->flowCallbackService->decodeToken($flowToken);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null,
     *     completionType?: string|null,
     *     reservationId?: int|null
     * }  $details
     */
    public function completeAfterWebBooking(
        ListCatalog $catalog,
        array $item,
        Reservation $reservation,
        array $details,
        ?string $flowToken
    ): void {
        $context = $this->flowContextFromToken($flowToken);
        if (! $context || $context['catalog_id'] !== $catalog->id) {
            return;
        }

        $contact = Contact::withoutGlobalScopes()->find($context['contact_id']);
        $flow = Flow::withoutGlobalScopes()->find($context['flow_id']);

        if (! $contact || ! $flow) {
            return;
        }

        $prefix = $this->bookingVariableService->resolvePrefixFromFlowNode($flow, $context['node_id']);
        $message = $this->catalogListingBookingService->buildBookingMessage($catalog, $item, $details);

        $this->bookingVariableService->storeOnContact(
            $contact,
            $flow->id,
            $prefix,
            $item,
            $details,
            $message
        );

        $this->bookingPendingService->clearPending($contact, $flow->id);
        $contact->setContactState($flow->id, 'selected_listing', json_encode($item));

        ResumeFlowFromListingInquiry::dispatch($flow->id, $contact->id, (string) ($item['id'] ?? ''))->onQueue('flows');
    }
}
