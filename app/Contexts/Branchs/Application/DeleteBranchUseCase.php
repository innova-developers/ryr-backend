<?php

namespace App\Contexts\Branchs\Application;

use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\User;

class DeleteBranchUseCase
{
    private BranchRepository $repository;

    public function __construct(BranchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return User[]
     */
    public function __invoke(int $id): bool
    {
        return $this->repository->delete($id);
    }
}
