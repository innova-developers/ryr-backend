<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Application\DTO\BulkAdjustPricesDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;

class BulkAdjustPricesUseCase
{
    public function __construct(
        private readonly DestinationRepository $repository
    ) {
    }

    public function __invoke(BulkAdjustPricesDTO $dto): int
    {
        return $this->repository->bulkAdjustPrices($dto);
    }
}
