<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Application\DTO\GetFranchisesFiltersDTO;
use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;

class GetFranchisesUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(?GetFranchisesFiltersDTO $filters = null): array
    {
        return $this->repository->get($filters);
    }
}
