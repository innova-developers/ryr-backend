<?php

namespace App\Contexts\Locations\Application;

use App\Contexts\Locations\Domain\Repositories\LocationsRepository;

class ListLocationsUseCase
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {}

    public function __invoke(): array
    {
        return $this->repository->findAll();
    }
}
