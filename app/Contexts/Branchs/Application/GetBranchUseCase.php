<?php

namespace App\Contexts\Branchs\Application;

use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\Branch;

class GetBranchUseCase
{
    private BranchRepository $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return array
     */
    public function __invoke(int $id): Branch
    {
        return $this->repository->findById($id);
    }

}
