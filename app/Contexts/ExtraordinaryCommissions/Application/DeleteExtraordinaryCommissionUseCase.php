<?php

namespace App\Contexts\ExtraordinaryCommissions\Application;

use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;

class DeleteExtraordinaryCommissionUseCase
{
    private ExtraordinaryCommissionRepository $repository;

    public function __construct(ExtraordinaryCommissionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
