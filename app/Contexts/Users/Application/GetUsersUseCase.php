<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\User;
use Illuminate\Support\Facades\Hash;

class GetUsersUseCase
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke():array
    {
        return $this->repository->get();
    }
}
