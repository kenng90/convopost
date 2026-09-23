<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('catalog:sync-stores')->hourly();
        $schedule->command('catalog:sync-availability')->hourly();
        $schedule->command('whatsapp-flows:mark-abandoned')->hourly();
        $schedule->command('campaigns:dispatch-scheduled')->everyMinute();
        $schedule->command('campaigns:check-completion')->everyFiveMinutes();
        $schedule->command('campaigns:process-recurring')->everyFifteenMinutes();
        $schedule->command('campaigns:flush-counters')->everyMinute();
        $schedule->command('voice-booking:expire-holds')->everyFiveMinutes();
        $schedule->command('outcomes:process-booking-no-shows')->hourly();
        $schedule->command('collections:advance')->everyFiveMinutes();
        $schedule->command('tiktok:refresh-tokens')->hourly();
        $schedule->command('social:refresh-tokens')->hourly();
        $schedule->command('social:publish-due')->everyMinute();
        $schedule->command('social:sync-analytics')->hourly();
        $schedule->command('social:sync-comments')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
