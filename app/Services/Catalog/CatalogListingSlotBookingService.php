<?php

namespace App\Services\Catalog;

use App\Models\Company;
use App\Models\ListCatalog;
use Carbon\Carbon;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\AvailabilityService;
use Modules\Reminders\Services\BookingPaymentService;
use Modules\Reminders\Support\BookingPaymentConfig;
use RuntimeException;

class CatalogListingSlotBookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected BookingPaymentService $bookingPaymentService,
        protected CatalogListingBookingCompletionService $bookingCompletionService,
        protected CatalogFlowNodeSettingsService $flowNodeSettingsService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     mode: string,
     *     source?: array<string, mixed>,
     *     message?: string
     * }
     */
    public function bookingConfig(ListCatalog $catalog, array $item, ?string $flowToken): array
    {
        $flowSettings = $this->flowNodeSettingsService->resolveListingNodeSettings($flowToken);
        $mode = $this->resolveBookingMode($catalog, $item, $flowSettings);

        if ($mode !== 'slots') {
            return [
                'mode' => $mode,
                'message' => $mode === 'inquiry'
                    ? 'Send an inquiry on WhatsApp.'
                    : 'Complete your booking request on WhatsApp.',
            ];
        }

        $source = $this->resolveBookableSource($catalog->company, $item);
        if (! $source) {
            return [
                'mode' => 'whatsapp',
                'message' => 'Online slot booking is not configured for this item.',
            ];
        }

        $paymentConfig = BookingPaymentConfig::fromSource($source);

        return [
            'mode' => 'slots',
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'timezone' => $source->timezone ?: config('app.timezone', 'UTC'),
                'default_duration_minutes' => (int) ($source->default_duration_minutes ?: 30),
                'duration_options' => $source->durationOptions(),
                'payment_required' => $paymentConfig['payment_required'],
                'payment_amount' => $paymentConfig['payment_amount'],
                'payment_total_amount' => $paymentConfig['payment_total_amount'],
                'payment_upfront_percent' => $paymentConfig['payment_upfront_percent'],
                'payment_currency' => $paymentConfig['payment_currency'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function availableDates(ListCatalog $catalog, array $item, ?int $durationMinutes = null): array
    {
        $source = $this->requireBookableSource($catalog->company, $item);
        $durationMinutes = $this->availabilityService->resolveDuration($source, $durationMinutes);
        $timezone = $source->timezone ?: config('app.timezone', 'UTC');
        $from = now($timezone)->startOfDay();
        $to = $from->copy()->addDays(max(1, (int) ($source->max_advance_days ?: 30)));

        return $this->availabilityService->availableDates($source, $from, $to, $durationMinutes);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     date: string,
     *     duration_minutes: int,
     *     duration_options: list<int>,
     *     slots: list<array<string, mixed>>
     * }
     */
    public function slotsForDate(ListCatalog $catalog, array $item, string $date, ?int $durationMinutes = null): array
    {
        $source = $this->requireBookableSource($catalog->company, $item);
        $durationMinutes = $this->availabilityService->resolveDuration($source, $durationMinutes);

        return [
            'date' => $date,
            'duration_minutes' => $durationMinutes,
            'duration_options' => $source->durationOptions(),
            'slots' => $this->availabilityService->slotsForDate($source, $date, $durationMinutes),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     reservation: array<string, mixed>,
     *     requires_action: bool,
     *     invoice_public_uuid?: string|null
     * }
     */
    public function bookSlot(
        ListCatalog $catalog,
        array $item,
        string $slotId,
        string $customerPhone,
        ?string $customerName,
        ?string $notes,
        ?int $durationMinutes,
        ?string $flowToken
    ): array {
        $source = $this->requireBookableSource($catalog->company, $item);
        $durationMinutes = $this->availabilityService->resolveDuration($source, $durationMinutes);
        $flowContext = $this->bookingCompletionService->flowContextFromToken($flowToken);

        $payload = [
            'phone' => $customerPhone,
            'name' => trim((string) $customerName) !== '' ? trim((string) $customerName) : 'Customer',
            'source' => $source->name,
            'slot_id' => $slotId,
            'duration_minutes' => $durationMinutes,
            'external_id' => 'catalog_listing:'.($item['id'] ?? ''),
            'booking_source' => 'catalog_public',
        ];

        if ($flowContext) {
            $payload['flow_id'] = $flowContext['flow_id'];
            $payload['flow_node_id'] = $flowContext['node_id'];
            $payload['flow_context'] = $flowContext;
        }

        try {
            $paymentResult = $this->bookingPaymentService->initiateAppointmentPayment($catalog->company, $payload);
        } catch (RuntimeException $exception) {
            throw new RuntimeException($exception->getMessage(), 409, $exception);
        }

        if ($paymentResult['requires_action']) {
            return [
                'requires_action' => true,
                'invoice_public_uuid' => $paymentResult['invoice']?->public_uuid,
                'reservation' => null,
            ];
        }

        /** @var Reservation $reservation */
        $reservation = $paymentResult['reservation'];
        $reservation->load(['source', 'appointmentStaffMember']);

        $details = $this->bookingDetailsFromReservation($reservation, $customerName, $customerPhone, $notes);

        $this->bookingCompletionService->completeAfterWebBooking(
            $catalog,
            $item,
            $reservation,
            $details,
            $flowToken
        );

        return [
            'requires_action' => false,
            'invoice_public_uuid' => null,
            'reservation' => $this->formatReservation($reservation),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function resolveBookableSource(Company $company, array $item): ?Source
    {
        $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];
        $sourceRef = $metadata['booking_source_id']
            ?? $metadata['reminders_source_id']
            ?? $item['booking_source_id']
            ?? $item['reminders_source_id']
            ?? $metadata['booking_source_name']
            ?? $item['booking_source_name']
            ?? null;

        if ($sourceRef === null || $sourceRef === '') {
            return null;
        }

        $query = Source::queryForCompany($company->id)
            ->where('is_bookable', true);

        if (is_numeric($sourceRef)) {
            return $query->where('id', (int) $sourceRef)->first();
        }

        return $query->where('name', (string) $sourceRef)->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function requireBookableSource(Company $company, array $item): Source
    {
        $source = $this->resolveBookableSource($company, $item);
        if (! $source) {
            throw new RuntimeException('This listing is not linked to a bookable service.');
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     completionType: string,
     *     bookingVariablePrefix: string,
     *     requirePreferredDateTime: bool,
     *     bookingBackend: string
     * }  $flowSettings
     */
    private function resolveBookingMode(ListCatalog $catalog, array $item, array $flowSettings): string
    {
        if (($flowSettings['completionType'] ?? 'booking') === 'inquiry') {
            return 'inquiry';
        }

        if ($catalog->isCommerce()) {
            return 'whatsapp';
        }

        if ($this->resolveBookableSource($catalog->company, $item)) {
            return 'slots';
        }

        return 'whatsapp';
    }

    /**
     * @return array{
     *     customerName: string|null,
     *     customerPhone: string,
     *     preferredDateTime: string,
     *     notes: string|null,
     *     completionType: string,
     *     reservationId: int
     * }
     */
    private function bookingDetailsFromReservation(
        Reservation $reservation,
        ?string $customerName,
        string $customerPhone,
        ?string $notes
    ): array {
        $timezone = $reservation->source?->timezone ?: config('app.timezone', 'UTC');
        $start = Carbon::parse($reservation->start_date)->timezone($timezone);

        return [
            'customerName' => $customerName,
            'customerPhone' => $customerPhone,
            'preferredDateTime' => $start->format('D, M j Y g:i A'),
            'notes' => $notes,
            'completionType' => 'booking',
            'reservationId' => $reservation->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatReservation(Reservation $reservation): array
    {
        $timezone = $reservation->source?->timezone ?: config('app.timezone', 'UTC');
        $start = Carbon::parse($reservation->start_date)->timezone($timezone);
        $end = Carbon::parse($reservation->end_date)->timezone($timezone);

        return [
            'id' => $reservation->id,
            'service' => $reservation->source?->name,
            'date_label' => $start->format('l, F j, Y'),
            'time_label' => $start->format('g:i A').' – '.$end->format('g:i A'),
            'duration_minutes' => $reservation->duration_minutes,
            'timezone' => $timezone,
            'status' => (int) $reservation->status,
        ];
    }
}
