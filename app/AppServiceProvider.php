<?php

namespace App;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contexts\Users\Domain\Repositories\UserRepository::class,
            \App\Contexts\Users\Infrastructure\Repositories\UserEloquentRepository::class
        );
        $this->app->bind(
            \App\Contexts\Branchs\Domain\Repositories\BranchRepository::class,
            \App\Contexts\Branchs\Infrastructure\Repositories\BranchEloquentRepository::class
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
