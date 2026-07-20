<?php

namespace Modules\Reminders\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Reminders\Models\Reservation;
use Modules\Reminders\Services\ReservationBookingService;

class SyncReservationCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  'create'|'update'|'delete'  $action
     */
    public function __construct(
        public int $reservationId,
        public string $action,
        public ?int $previousCalendarUserId = null,
        public ?string $previousEventId = null,
        public ?string $previousCalendarId = null,
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ReservationBookingService $bookingService): void
    {
        $reservation = Reservation::withoutGlobalScopes()
            ->with(['contact', 'source', 'appointmentStaffMember'])
            ->find($this->reservationId);

        if (! $reservation && $this->action !== 'delete') {
            return;
        }

        try {
            match ($this->action) {
                'create' => $bookingService->performCalendarCreate($reservation),
                'update' => $bookingService->performCalendarUpdate(
                    $reservation,
                    $this->previousCalendarUserId,
                    $this->previousEventId,
                    $this->previousCalendarId
                ),
                'delete' => $bookingService->performCalendarDelete(
                    $reservation,
                    $this->previousCalendarUserId,
                    $this->previousEventId,
                    $this->previousCalendarId
                ),
                default => Log::warning('Unknown reservation calendar sync action', [
                    'action' => $this->action,
                    'reservation_id' => $this->reservationId,
                ]),
            };
        } catch (\Throwable $exception) {
            Log::error('Reservation calendar sync job failed', [
                'reservation_id' => $this->reservationId,
                'action' => $this->action,
                'error' => $exception->getMessage(),
            ]);

            if ($reservation) {
                $reservation->update([
                    'google_calendar_sync_error' => $exception->getMessage(),
                ]);
            }

            throw $exception;
        }
    }
}
