<?php

namespace App\Contexts\ExtraordinaryCommissions\Application;

use App\Contexts\ExtraordinaryCommissions\Application\DTO\GetExtraordinaryCommissionByOriginAndDestinationDTO;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;

class GetExtraordinaryCommissionByOriginAndDestinationUseCase
{
    public function __construct(
        private readonly ExtraordinaryCommissionRepository $repository,
    ) {
    }

    public function __invoke(GetExtraordinaryCommissionByOriginAndDestinationDTO $dto): array
    {
        return $this->repository->findByOriginAndDestination($dto);
    }

}
