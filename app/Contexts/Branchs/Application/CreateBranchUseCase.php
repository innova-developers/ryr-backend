<?php

namespace App\Contexts\Branchs\Application;

use App\Contexts\Branchs\Application\DTO\CreateBranchDTO;
use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\Branch;
use App\Shared\Models\User;

class CreateBranchUseCase
{
    private BranchRepository $repository;

    public function __construct(BranchRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return User[]
     */
    public function __invoke(CreateBranchDTO $dto): Branch
    {
        return $this->repository->create($dto);
    }
}
