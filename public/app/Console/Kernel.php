<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Interest calculation - Daily at Midnight
        $schedule->command('savings:process-interest')->daily();

        // Target Auto-Save - Daily (the service handles internal frequency check)
        $schedule->command('savings:auto-save')->daily();

        // EduSave Payouts - Daily
        $schedule->command('edusave:process-payouts')->daily();

        // USDT EasyEarn - Credit interest on 10th of every month at 2:00 AM
        $schedule->command('usdt:easyearn:credit-interest')
                 ->monthlyOn(10, '02:00')
                 ->timezone('Africa/Lagos');

        // USDT EasyEarn - Check for matured investments daily at 3:00 AM
        $schedule->command('usdt:easyearn:check-maturity')
                 ->dailyAt('03:00')
                 ->timezone('Africa/Lagos');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
