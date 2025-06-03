<?php

namespace App\Contexts\Branchs\Application;

use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\User;

class GetBranchsUseCase
{
    private BranchRepository $repository;

    public function __construct(BranchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return User[]
     */
    public function __invoke(): array
    {
        return $this->repository->get();
    }
}
