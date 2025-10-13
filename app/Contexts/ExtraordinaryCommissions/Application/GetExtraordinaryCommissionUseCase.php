<?php

namespace App\Contexts\ExtraordinaryCommissions\Application;

use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Shared\Models\ExtraordinaryCommission;

class GetExtraordinaryCommissionUseCase
{
    private ExtraordinaryCommissionRepository $repository;

    public function __construct(ExtraordinaryCommissionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return ExtraordinaryCommission
     */
    public function __invoke(int $id): ExtraordinaryCommission
    {
        return $this->repository->findById($id);
    }
}
