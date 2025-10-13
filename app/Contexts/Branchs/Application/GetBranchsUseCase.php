<?php

namespace App\Contexts\Branchs\Application;

use App\Contexts\Branchs\Application\DTO\GetBranchesFiltersDTO;
use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\Branch;

class GetBranchsUseCase
{
    private BranchRepository $repository;

    public function __construct(BranchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return Branch[]
     */
    public function __invoke(?GetBranchesFiltersDTO $filters = null): array
    {
        return $this->repository->get($filters);
    }
}
