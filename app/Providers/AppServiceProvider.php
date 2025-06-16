<?php

namespace App\Providers;

use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Destinations\Infrastructure\Repositories\DestinationEloquentRepository;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Contexts\Transports\Infrastructure\Repositories\TransportEloquentRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DestinationRepository::class, DestinationEloquentRepository::class);
        $this->app->bind(TransportRepository::class, TransportEloquentRepository::class);
    }

    public function boot()
    {
        //
    }
}
