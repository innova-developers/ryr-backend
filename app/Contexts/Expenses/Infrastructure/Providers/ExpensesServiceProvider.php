<?php

namespace App\Contexts\Expenses\Infrastructure\Providers;

use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;
use App\Contexts\Expenses\Infrastructure\Repositories\ExpensesEloquentRepository;
use Illuminate\Support\ServiceProvider;

class ExpensesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExpensesRepository::class, ExpensesEloquentRepository::class);
    }

    public function boot(): void
    {
        //
    }
} 