<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Providers;

use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\CurrentAccount\Infrastructure\Repositories\CurrentAccountEloquentRepository;
use Illuminate\Support\ServiceProvider;

class CurrentAccountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CurrentAccountRepository::class, CurrentAccountEloquentRepository::class);
    }
}
