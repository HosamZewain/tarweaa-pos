<?php

namespace App\Providers;

use App\Services\CashManagementService;
use App\Services\DrawerSessionService;
use App\Services\OrderService;
use App\Services\ShiftService;
use Illuminate\Support\ServiceProvider;

/**
 * Registers all POS domain services as singletons.
 * Register this in config/app.php -> providers[].
 */
class PosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShiftService::class);

        $this->app->singleton(DrawerSessionService::class);

        $this->app->singleton(CashManagementService::class);

        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService(
                drawerService: $app->make(DrawerSessionService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
