<?php

namespace App;

use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Contexts\Branchs\Infrastructure\Repositories\BranchEloquentRepository;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\Commissions\Infrastructure\Repositories\CommissionsEloquentRepository;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Customers\Infrastructure\Repositories\CustomerEloquentRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Destinations\Infrastructure\Repositories\DestinationEloquentRepository;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Contexts\ExtraordinaryCommissions\Infrastructure\Repositories\ExtraordinaryCommissionEloquentRepository;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Contexts\Locations\Infrastructure\Repositories\LocationsEloquentRepository;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Contexts\Users\Infrastructure\Repositories\UserEloquentRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            UserRepository::class,
            UserEloquentRepository::class
        );
        $this->app->bind(
            BranchRepository::class,
            BranchEloquentRepository::class
        );
        $this->app->bind(
            CustomerRepository::class,
            CustomerEloquentRepository::class
        );
        $this->app->bind(
            DestinationRepository::class,
            DestinationEloquentRepository::class
        );
        $this->app->bind(
            ExtraordinaryCommissionRepository::class,
            ExtraordinaryCommissionEloquentRepository::class
        );
        $this->app->bind(
            CommissionsRepository::class,
            CommissionsEloquentRepository::class
        );
        $this->app->bind(
            LocationsRepository::class,
            LocationsEloquentRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
