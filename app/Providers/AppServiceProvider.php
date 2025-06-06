<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Destinations\Infrastructure\Repositories\DestinationEloquentRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DestinationRepository::class, DestinationEloquentRepository::class);
    }

    public function boot()
    {
        //
    }
}
