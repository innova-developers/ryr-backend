<?php

namespace App\Contexts\Locations\Application;

use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Location;

class CreateLocationUseCase
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {}

    public function __invoke(CreateLocationDTO $dto): Location
    {
        return $this->repository->create($dto);
    }
}
