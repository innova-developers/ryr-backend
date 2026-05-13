<?php

namespace App\Contexts\Franchises\Application;

use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;

class GetSettlementReportUseCase
{
    public function __construct(private FranchiseRepository $repository)
    {
    }

    public function __invoke(int $franchiseId, string $dateFrom, string $dateTo): array
    {
        return $this->repository->getSettlementReport($franchiseId, $dateFrom, $dateTo);
    }
}
