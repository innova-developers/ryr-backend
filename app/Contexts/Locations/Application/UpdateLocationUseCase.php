<?php

namespace App\Contexts\Locations\Application;

use App\Contexts\Locations\Application\DTOs\UpdateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Location;

class UpdateLocationUseCase
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {}

    public function __invoke(UpdateLocationDTO $dto): Location
    {
        return $this->repository->update($dto);
    }
}
