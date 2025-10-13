<?php

namespace App\Contexts\IncomeCategories\Infrastructure\Providers;

use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;
use App\Contexts\IncomeCategories\Infrastructure\Repositories\IncomeCategoriesEloquentRepository;
use Illuminate\Support\ServiceProvider;

class IncomeCategoriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IncomeCategoriesRepository::class, IncomeCategoriesEloquentRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
