<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;

class DeleteFranchiseUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
