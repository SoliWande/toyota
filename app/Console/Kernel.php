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
        // Opt-in only after the operator has reviewed a dry run and approved retention.
        $schedule->command('dealer-registration:prune-receipts')->hourly()->withoutOverlapping()
            ->when(fn () => config('dealer-registration.receipt_cleanup_enabled', false));
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
