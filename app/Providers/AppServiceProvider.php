<?php

namespace App\Providers;

use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\Commissions\Infrastructure\Repositories\CommissionsEloquentRepository;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\CurrentAccount\Infrastructure\Repositories\CurrentAccountEloquentRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Destinations\Infrastructure\Repositories\DestinationEloquentRepository;
use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Contexts\ExpenseCategories\Infrastructure\Repositories\ExpenseCategoryEloquentRepository;
use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;
use App\Contexts\Expenses\Infrastructure\Repositories\ExpensesEloquentRepository;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Contexts\Transports\Infrastructure\Repositories\TransportEloquentRepository;
use App\Services\CommissionNotificationService;
use App\Services\WhatsAppService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DestinationRepository::class, DestinationEloquentRepository::class);
        $this->app->bind(TransportRepository::class, TransportEloquentRepository::class);
        $this->app->bind(ExpensesRepository::class, ExpensesEloquentRepository::class);
        $this->app->bind(ExpenseCategoryRepository::class, ExpenseCategoryEloquentRepository::class);
        $this->app->bind(CommissionsRepository::class, CommissionsEloquentRepository::class);
        $this->app->bind(CurrentAccountRepository::class, CurrentAccountEloquentRepository::class);

        // Registrar servicios de notificación
        $this->app->singleton(WhatsAppService::class);
        $this->app->singleton(CommissionNotificationService::class);
    }

    public function boot()
    {
        //
    }
}
