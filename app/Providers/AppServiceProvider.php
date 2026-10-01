<?php

namespace App\Providers;

use App\Models\BasicControl;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
         $this->app->bind(
        \App\Services\Savings\Contracts\SavingsFundingProviderInterface::class,
        \App\Services\Savings\SafeHavenSavingsProvider::class
    );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * Laravel UI / pagination.
         */
        Paginator::useBootstrapFive();

        /*
         * Prevent "Specified key was too long" errors on older MySQL versions.
         */
        Schema::defaultStringLength(191);

        /*
         * Force HTTPS in production.
         */
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        /*
         * Do not access the database while Laravel is booting
         * console commands such as:
         *
         * - composer install
         * - composer dump-autoload
         * - php artisan package:discover
         * - php artisan config:cache
         * - php artisan route:cache
         *
         * This prevents deployment from failing when the database
         * is temporarily unavailable.
         */
        if ($this->app->runningInConsole()) {
            return;
        }

        /*
         * Only access BasicControl after Laravel is running as a
         * normal web application.
         */
        try {
            if (! Schema::hasTable('basic_controls')) {
                return;
            }

            $basicControl = Cache::rememberForever(
                'basic_control',
                static function () {
                    return BasicControl::first();
                }
            );

            /*
             * Register BasicControl in the service container.
             */
            $this->app->instance(
                BasicControl::class,
                $basicControl
            );

            /*
             * Make BasicControl available to all Blade views.
             */
            view()->share(
                'basicControl',
                $basicControl
            );
        } catch (\Throwable $e) {
            /*
             * Do not prevent the application from booting if the
             * database is temporarily unavailable.
             *
             * Log the error instead.
             */
            report($e);
        }
    }
}