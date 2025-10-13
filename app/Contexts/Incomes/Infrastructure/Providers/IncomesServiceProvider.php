<?php

namespace App\Contexts\Incomes\Infrastructure\Providers;

use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;
use App\Contexts\Incomes\Infrastructure\Repositories\IncomesEloquentRepository;
use Illuminate\Support\ServiceProvider;

class IncomesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IncomesRepository::class, IncomesEloquentRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
