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
use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Contexts\ExpenseCategories\Infrastructure\Repositories\ExpenseCategoryEloquentRepository;
use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;
use App\Contexts\Expenses\Infrastructure\Repositories\ExpensesEloquentRepository;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Contexts\ExtraordinaryCommissions\Infrastructure\Repositories\ExtraordinaryCommissionEloquentRepository;
use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Contexts\Franchises\Infrastructure\Repositories\FranchiseEloquentRepository;
use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;
use App\Contexts\IncomeCategories\Infrastructure\Repositories\IncomeCategoriesEloquentRepository;
use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;
use App\Contexts\Incomes\Infrastructure\Repositories\IncomesEloquentRepository;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Contexts\Locations\Infrastructure\Repositories\LocationsEloquentRepository;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Contexts\Transports\Infrastructure\Repositories\TransportEloquentRepository;
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
        $this->app->bind(
            TransportRepository::class,
            TransportEloquentRepository::class
        );
        $this->app->bind(
            ExpensesRepository::class,
            ExpensesEloquentRepository::class
        );
        $this->app->bind(
            ExpenseCategoryRepository::class,
            ExpenseCategoryEloquentRepository::class
        );
        $this->app->bind(
            IncomesRepository::class,
            IncomesEloquentRepository::class
        );
        $this->app->bind(
            IncomeCategoriesRepository::class,
            IncomeCategoriesEloquentRepository::class
        );
        $this->app->bind(
            FranchiseRepository::class,
            FranchiseEloquentRepository::class
        );

        // Telescope es require-dev: registrar su provider solo si el paquete está instalado
        // (no en producción con composer install --no-dev). Evita "Class ... not found".
        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
            && class_exists(\App\Providers\TelescopeServiceProvider::class)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
