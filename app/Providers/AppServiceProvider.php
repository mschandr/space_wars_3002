<?php

namespace App\Providers;

use App\Services\Contracts\ContractExpiryService;
use App\Services\Contracts\ContractGenerationService;
use App\Services\Contracts\ContractService;
use App\Services\Contracts\ReputationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Contract system services
        $this->app->singleton(ReputationService::class, function ($app) {
            return new ReputationService();
        });

        $this->app->singleton(ContractService::class, function ($app) {
            return new ContractService(
                $app->make(ReputationService::class)
            );
        });

        $this->app->singleton(ContractGenerationService::class, function ($app) {
            return new ContractGenerationService();
        });

        $this->app->singleton(ContractExpiryService::class, function ($app) {
            return new ContractExpiryService(
                $app->make(ReputationService::class)
            );
        });

        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
