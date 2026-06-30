<?php

namespace App\Services\Catalog;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\Source;
use Modules\Reminders\Services\ReservationBookingService;
use Modules\Reminders\Services\StaffAssignmentService;

class CatalogListingBookingReservationService
{
    public function __construct(
        protected ReservationBookingService $reservationBookingService,
        protected StaffAssignmentService $staffAssignmentService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array{
     *     customerName?: string|null,
     *     customerPhone?: string|null,
     *     preferredDateTime?: string|null,
     *     notes?: string|null
     * }  $details
     */
    public function tryCreateReservation(
        Company $company,
        array $item,
        array $details,
        string $bookingBackend
    ): ?int {
        if ($bookingBackend !== 'reminders') {
            return null;
        }

        $source = $this->resolveSource($company, $item);
        if (! $source) {
            Log::info('Catalog booking: no reminders source linked to listing item', [
                'item_id' => $item['id'] ?? null,
            ]);

            return null;
        }

        $phone = trim((string) ($details['customerPhone'] ?? ''));
        if ($phone === '') {
            Log::info('Catalog booking: reminders booking skipped, phone missing');

            return null;
        }

        $preferred = trim((string) ($details['preferredDateTime'] ?? ''));
        if ($preferred === '') {
            Log::info('Catalog booking: reminders booking skipped, preferred date/time missing');

            return null;
        }

        try {
            $timezone = $source->timezone ?: config('app.timezone', 'UTC');
            $start = Carbon::parse($preferred, $timezone);
            $durationMinutes = (int) ($source->default_duration_minutes ?: 30);
            $end = $start->copy()->addMinutes($durationMinutes);

            $member = $this->staffAssignmentService->assign($source, $start, $durationMinutes);

            $reservation = $this->reservationBookingService->book($company, [
                'phone' => $phone,
                'name' => trim((string) ($details['customerName'] ?? '')) !== ''
                    ? trim((string) $details['customerName'])
                    : 'Customer',
                'source' => $source->id,
                'start_date' => $start->toIso8601String(),
                'end_date' => $end->toIso8601String(),
                'duration_minutes' => $durationMinutes,
                'appointment_staff_id' => $member->id,
                'staff_user_id' => $member->user_id,
                'external_id' => 'catalog_listing:'.($item['id'] ?? ''),
            ]);

            return $reservation->id;
        } catch (\Throwable $e) {
            Log::warning('Catalog booking: reminders reservation failed', [
                'item_id' => $item['id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveSource(Company $company, array $item): ?Source
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

        $query = Source::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('is_bookable', true);

        if (is_numeric($sourceRef)) {
            return $query->where('id', (int) $sourceRef)->first();
        }

        return $query->where('name', (string) $sourceRef)->first();
    }
}
