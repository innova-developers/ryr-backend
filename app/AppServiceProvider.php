<?php

namespace App;

use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Contexts\Branchs\Infrastructure\Repositories\BranchEloquentRepository;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Customers\Infrastructure\Repositories\CustomerEloquentRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Destinations\Infrastructure\Repositories\DestinationEloquentRepository;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
