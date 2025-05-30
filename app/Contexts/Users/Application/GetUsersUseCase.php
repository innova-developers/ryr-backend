<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\User;

class GetUsersUseCase
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return User[]
     */
    public function __invoke():array
    {
        return $this->repository->get();
    }
}
