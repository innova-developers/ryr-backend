<?php

namespace App\Contexts\Users\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Contexts\Users\Infrastructure\Repositories\EloquentUserRepository;

class UserServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
    }
}
