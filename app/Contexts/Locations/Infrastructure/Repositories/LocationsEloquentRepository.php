<?php

namespace App\Contexts\Locations\Infrastructure\Repositories;

use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Application\DTOs\UpdateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository as LocationsRepositoryInterface;
use App\Shared\Models\Location;

class LocationsEloquentRepository implements LocationsRepositoryInterface
{
    public function findAll(): array
    {
        return Location::all()->toArray();
    }

    public function findById(int $id): Location
    {
        return Location::find($id);
    }

    /**
     * @throws \Exception
     */
    public function create(CreateLocationDTO $dto): Location
    {
        try {
            $location = new Location();
            $location->name = $dto->name;
            $location->address = $dto->address;
            $location->origin = $dto->origin;
            $location->phone = $dto->phone;
            $location->map = $dto->map;
            $location->schedule = $dto->schedule;
            $location->observation = $dto->observation;
            $location->save();

            return $location;
        } catch (\Exception $exception) {
            throw new \Exception('Error creating location: ' . $exception->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function update(UpdateLocationDTO $dto): Location
    {
        $location = Location::findOrFail($dto->id);
        $location->update([
            'name' => $dto->name,
            'address' => $dto->address,
            'origin' => $dto->origin,
            'phone' => $dto->phone,
            'map' => $dto->map,
            'schedule' => $dto->schedule,
            'observation' => $dto->observation,
        ]);

        return Location::find($dto->id);

    }

    public function delete(int $id): void
    {
        $location = Location::findOrFail($id);
        $location->delete();
    }
}
