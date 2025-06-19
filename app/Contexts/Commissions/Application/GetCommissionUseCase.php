<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\Mappers\CommissionsMapper;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;

class GetCommissionUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionRepository
    ) {
    }

    public function __invoke(int $id): array
    {
        $commission = $this->commissionRepository->findById($id);

        return CommissionsMapper::fromDomainToIndiividualArray($commission);

    }
}
