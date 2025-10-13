<?php

namespace App\Contexts\Locations\Application;

use App\Contexts\Locations\Application\DTOs\GetLocationsFiltersDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;

class ListLocationsUseCase
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {
    }

    public function __invoke(?GetLocationsFiltersDTO $filters = null): array
    {
        return $this->repository->findAll($filters);
    }
}
