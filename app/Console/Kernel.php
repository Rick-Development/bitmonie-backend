<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\EncryptKycVerificationData;

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
        $schedule->command('savings:process-interest')
                 ->dailyAt('00:00')
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping(120)
                 ->appendOutputTo(storage_path('logs/savings-scheduler.log'));

        // Target Auto-Save - Daily (the service handles internal frequency check)
        $schedule->command('savings:auto-save')
                 ->daily()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping();

        // EduSave Payouts - Daily
        $schedule->command('edusave:process-payouts')->daily();

        // USDT EasyEarn - Credit daily accrued interest.
        $schedule->command('usdt:easyearn:credit-interest')
                 ->dailyAt('00:10')
                 ->timezone('Africa/Lagos');

        // USDT EasyEarn - Check for matured investments daily at 3:00 AM
        $schedule->command('usdt:easyearn:check-maturity')
                 ->dailyAt('03:00')
                 ->timezone('Africa/Lagos');

        // Ramp sell recovery - resumes stuck sell payout legs and refreshes provider status.
        $schedule->command('ramp:recover-stuck-sells --minutes=3 --limit=50')
                 ->everyFiveMinutes()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping(10)
                 ->appendOutputTo(storage_path('logs/ramp-recovery.log'));

        // AutoSave scheduled deductions.
        $schedule->command('autosave:process-scheduled --limit=100')
                 ->everyMinute()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping(10)
                 ->appendOutputTo(storage_path('logs/autosave-scheduler.log'));

        // EasyEarn maturity and midpoint notifications.
        $schedule->command('easyearn:process --limit=100')
                 ->everyMinute()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping(10)
                 ->appendOutputTo(storage_path('logs/easyearn-scheduler.log'));

        // Fail pending crypto trades (older than 30m)
        $schedule->command('crypto:fail-pending-trades')
                 ->everyMinute()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/crypto-trade-failures.log'));


                 //handle matured autosave
                  $schedule->command('autosave:process-matured')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

        // Cancel stale borrow requests (older than 48h)
        $schedule->command('loans:cancel-stale-requests')
                 ->hourly()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/loans-cancellation.log'));
if (config('services.yellow_card.api_key') && config('services.yellow_card.secret_key')) {
            $schedule->command('yellow-card:sync-coverage')
                     ->dailyAt('02:30')
                     ->timezone('Africa/Lagos')
                     ->withoutOverlapping(60)
                     ->appendOutputTo(storage_path('logs/yellow-card-coverage.log'));
        }

        // Auto-fulfill expired/stuck P2P orders in escrow
        $schedule->command('p2p:process-expired-escrows')
                 ->everyFiveMinutes()
                 ->timezone('Africa/Lagos')
                 ->withoutOverlapping(10)
                 ->appendOutputTo(storage_path('logs/p2p-escrow-scheduler.log'));

Schedule::command('autosave:reconcile')
    ->everyFiveMinutes()
    ->timezone('Africa/Lagos')
    ->withoutOverlapping(10)
    ->appendOutputTo(
        storage_path('logs/autosave-reconciliation.log')
    );

                 //encrypt data
        $schedule->command('kyc:encrypt-data --limit=100') 
        ->everyFiveMinutes() ->timezone('Africa/Lagos') 
        ->withoutOverlapping(10) ->appendOutputTo(storage_path('logs/kyc-encryption.log'));
       
                 
        // $schedule->command('ramp:reconcile')
        //         ->everyMinute()
        //         ->withoutOverlapping(2)
        //         ->runInBackground();
                 
        // $schedule->command('wallet:repair-reserved')
        //         ->everyFiveMinutes()
        //         ->timezone('Africa/Lagos')
        //         ->withoutOverlapping()
        //         ->runInBackground();
                 
        // $schedule->command('ramp:reverse-failed-offramp')
        //         ->everyFiveMinutes()
        //         ->timezone('Africa/Lagos')
        //         ->withoutOverlapping()
        //         ->runInBackground();
                
                
                
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
