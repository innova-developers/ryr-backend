<?php

namespace App\Contexts\Branchs\Application;

use App\Contexts\Branchs\Application\DTO\UpdateBranchDTO;
use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Shared\Models\Branch;

class UpdateBranchUseCase
{
    private BranchRepository $repository;

    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param UpdateBranchDTO $dto
     * @return Branch
     */
    public function __invoke(UpdateBranchDTO $dto): Branch
    {
        return $this->repository->update($dto);
    }

}
