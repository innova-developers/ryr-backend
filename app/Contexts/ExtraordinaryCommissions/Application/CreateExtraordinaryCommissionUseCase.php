<?php

namespace App\Contexts\ExtraordinaryCommissions\Application;

use App\Contexts\ExtraordinaryCommissions\Application\DTO\CreateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Shared\Models\ExtraordinaryCommission;

class CreateExtraordinaryCommissionUseCase
{
    public function __construct(
        private readonly ExtraordinaryCommissionRepository $repository
    ) {
    }
    public function __invoke(CreateExtraordinaryCommissionDTO $dto): ExtraordinaryCommission
    {
        return $this->repository->create($dto);
    }
}
