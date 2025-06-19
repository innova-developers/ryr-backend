<?php

namespace App\Contexts\Locations\Domain\Repositories;

use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Application\DTOs\UpdateLocationDTO;
use App\Shared\Models\Location;

interface LocationsRepository
{
    public function findAll(): array;
    public function findById(int $id): Location;
    public function create(CreateLocationDTO $dto): Location;
    public function update(UpdateLocationDTO $dto): Location;
    public function delete(int $id): void;
}
