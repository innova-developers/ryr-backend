<?php

namespace App\Contexts\Users\Infrastructure\Providers;

use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Contexts\Users\Infrastructure\Repositories\UserEloquentRepository;
use Illuminate\Support\ServiceProvider;

class UserServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(UserRepository::class, UserEloquentRepository::class);
    }
}
