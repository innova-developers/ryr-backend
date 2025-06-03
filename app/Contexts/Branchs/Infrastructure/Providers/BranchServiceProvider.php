<?php

namespace App\Contexts\Branchs\Infrastructure\Providers;

use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Contexts\Branchs\Infrastructure\Repositories\BranchEloquentRepository;
use Illuminate\Support\ServiceProvider;

class BranchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BranchRepository::class, BranchEloquentRepository::class);
    }
}
