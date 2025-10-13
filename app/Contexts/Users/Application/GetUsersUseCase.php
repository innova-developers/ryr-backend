<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Application\DTO\GetUsersFiltersDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;

class GetUsersUseCase
{
    private UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array
     */
    public function __invoke(?GetUsersFiltersDTO $filters = null): array
    {
        return $this->repository->get($filters);
    }
}
