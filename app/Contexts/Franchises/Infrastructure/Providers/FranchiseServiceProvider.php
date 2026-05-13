<?php

namespace App\Contexts\Franchises\Infrastructure\Providers;

use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Contexts\Franchises\Infrastructure\Repositories\FranchiseEloquentRepository;
use Illuminate\Support\ServiceProvider;

class FranchiseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FranchiseRepository::class, FranchiseEloquentRepository::class);
    }
}
