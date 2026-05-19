<?php

namespace App\Providers;

use App\Interfaces\FixtureServiceInterface;
use App\Interfaces\PredictionServiceInterface;
use App\Interfaces\SimulationServiceInterface;
use App\Interfaces\StandingServiceInterface;
use App\Services\FixtureService;
use App\Services\PredictionService;
use App\Services\SimulationService;
use App\Services\StandingService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * Binds interfaces to concrete implementations (Dependency Inversion Principle).
     */
    public function register(): void
    {
        // Standing must be registered first (SimulationService depends on it)
        $this->app->bind(StandingServiceInterface::class, StandingService::class);

        $this->app->bind(FixtureServiceInterface::class, FixtureService::class);

        $this->app->bind(SimulationServiceInterface::class, function ($app) {
            return new SimulationService(
                $app->make(StandingServiceInterface::class)
            );
        });

        $this->app->bind(PredictionServiceInterface::class, PredictionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
