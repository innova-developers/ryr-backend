<?php

namespace App\Contexts\Locations\Application;

use App\Contexts\Locations\Domain\Repositories\LocationsRepository;

class GetLocationsByOriginUseCase
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {
    }

    public function __invoke(string $origin): array
    {
        return $this->repository->findByOrigin($origin);
    }
}
