<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Application\DTO\UpdateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\User;

class UpdateUserUseCase
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(UpdateUserDTO $dto): User
    {
        return $this->repository->update($dto);
    }
}
