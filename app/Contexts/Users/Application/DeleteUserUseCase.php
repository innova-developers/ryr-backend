<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\User;

class DeleteUserUseCase
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return User[]
     */
    public function __invoke(int $id):array
    {
        return $this->repository->delete($id);
    }
}
