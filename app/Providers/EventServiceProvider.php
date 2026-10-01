<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use App\Listeners\DispatchAutosavePercentageDeductions;
use App\Listeners\CaptureSharedReferralCommission;
use App\Models\OrderTransaction;
use App\Models\GraphTransaction;
use App\Models\RampTransaction;
use App\Models\Transaction;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        'eloquent.created: ' . OrderTransaction::class => [
            DispatchAutosavePercentageDeductions::class,
            CaptureSharedReferralCommission::class,
        ],
        'eloquent.created: ' . Transaction::class => [
            CaptureSharedReferralCommission::class,
        ],
        'eloquent.updated: ' . Transaction::class => [
            CaptureSharedReferralCommission::class,
        ],
        'eloquent.created: ' . RampTransaction::class => [
            CaptureSharedReferralCommission::class,
        ],
        'eloquent.updated: ' . RampTransaction::class => [
            CaptureSharedReferralCommission::class,
        ],
        'eloquent.created: ' . GraphTransaction::class => [
            CaptureSharedReferralCommission::class,
        ],
        'eloquent.updated: ' . GraphTransaction::class => [
            CaptureSharedReferralCommission::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}
