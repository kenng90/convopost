<?php

namespace App\Console\Commands;

use App\Services\VoiceBooking\VoiceCallBookingService;
use Illuminate\Console\Command;

class ExpireVoiceBookingHolds extends Command
{
    protected $signature = 'voice-booking:expire-holds';

    protected $description = 'Cancel voice AI bookings with expired payment holds';

    public function handle(VoiceCallBookingService $bookingService): int
    {
        $count = $bookingService->expirePendingHolds();
        $this->info("Expired {$count} pending voice booking hold(s).");

        return self::SUCCESS;
    }
}
