<?php

namespace Vendor\UserDiscounts;

use Illuminate\Support\ServiceProvider;
use Vendor\UserDiscounts\Services\DiscountService;

class UserDiscountsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/user-discounts.php' => config_path('user-discounts.php'),
        ], 'user-discounts-config');

        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'user-discounts-migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'user-discounts');

        if ($this->app->runningInConsole()) {
            $this->commands([
                // Add console commands here if needed
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/user-discounts.php',
            'user-discounts'
        );

        $this->app->singleton(DiscountService::class, function ($app) {
            return new DiscountService($app['db']);
        });

        $this->app->alias(DiscountService::class, 'user-discounts');
    }
}
