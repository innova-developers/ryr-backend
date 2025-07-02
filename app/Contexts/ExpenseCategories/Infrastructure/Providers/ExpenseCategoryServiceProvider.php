<?php

namespace App\Contexts\ExpenseCategories\Infrastructure\Providers;

use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Contexts\ExpenseCategories\Infrastructure\Repositories\ExpenseCategoryEloquentRepository;
use Illuminate\Support\ServiceProvider;

class ExpenseCategoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExpenseCategoryRepository::class, ExpenseCategoryEloquentRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
