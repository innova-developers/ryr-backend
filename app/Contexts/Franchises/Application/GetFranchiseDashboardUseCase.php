<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;

class GetFranchiseDashboardUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(int $franchiseId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->repository->getDashboardStats($franchiseId, $dateFrom, $dateTo);
    }
}
