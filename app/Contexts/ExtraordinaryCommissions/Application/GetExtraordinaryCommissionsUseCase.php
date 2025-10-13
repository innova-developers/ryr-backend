<?php

namespace App\Contexts\ExtraordinaryCommissions\Application;

use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Shared\Models\ExtraordinaryCommission;

class GetExtraordinaryCommissionsUseCase
{
    private ExtraordinaryCommissionRepository $repository;

    public function __construct(ExtraordinaryCommissionRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return ExtraordinaryCommission[]
     */
    public function __invoke(): array
    {
        return $this->repository->get();
    }
}
