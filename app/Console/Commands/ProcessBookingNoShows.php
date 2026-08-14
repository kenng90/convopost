<?php

namespace App\Console\Commands;

use App\Services\Outcomes\BookingNoShowService;
use Illuminate\Console\Command;

class ProcessBookingNoShows extends Command
{
    protected $signature = 'outcomes:process-booking-no-shows {--grace=60 : Minutes after end_date before marking no-show}';

    protected $description = 'Move completed bookings without attendance into the Booking Convert No-show stage';

    public function handle(BookingNoShowService $service): int
    {
        $result = $service->process(null, (int) $this->option('grace'));

        $this->info("Processed {$result['processed']} reservation(s); moved {$result['moved']} to No-show.");

        return self::SUCCESS;
    }
}
