<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Shared\Models\Franchise;

class GetFranchiseUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(int $id): ?Franchise
    {
        return $this->repository->findById($id);
    }
}
