<?php

namespace App\Contexts\ExtraordinaryCommissions\Application;

use App\Contexts\ExtraordinaryCommissions\Application\DTO\UpdateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Shared\Models\ExtraordinaryCommission;

class UpdateExtraordinaryCommissionUseCase
{
    public function __construct(
        private readonly ExtraordinaryCommissionRepository $repository
    ) {
    }
    public function __invoke(UpdateExtraordinaryCommissionDTO $dto): ExtraordinaryCommission
    {
        return $this->repository->update($dto);
    }
}
