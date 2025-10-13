<?php

namespace App\Contexts\Locations\Infrastructure\Repositories;

use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Application\DTOs\GetLocationsFiltersDTO;
use App\Contexts\Locations\Application\DTOs\UpdateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository as LocationsRepositoryInterface;
use App\Shared\Models\Location;

class LocationsEloquentRepository implements LocationsRepositoryInterface
{
    public function findAll(?GetLocationsFiltersDTO $filters = null): array
    {
        $query = Location::query();

        // Aplicar filtro de búsqueda
        if ($filters && $filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters->search . '%')
                  ->orWhere('address', 'like', '%' . $filters->search . '%')
                  ->orWhere('origin', 'like', '%' . $filters->search . '%');
            });
        }

        // Aplicar ordenamiento
        if ($filters && $filters->sortBy) {
            $allowedSortFields = ['name', 'address', 'origin', 'created_at'];
            if (in_array($filters->sortBy, $allowedSortFields)) {
                $query->orderBy($filters->sortBy, $filters->sortDirection);
            }
        } else {
            $query->orderBy('name', 'asc');
        }

        // Aplicar paginación
        if ($filters) {
            $perPage = min($filters->perPage, 100); // Limitar a máximo 100 por página
            $locations = $query->paginate($perPage, ['*'], 'page', $filters->page);

            return [
                'data' => $locations->items(),
                'pagination' => [
                    'current_page' => $locations->currentPage(),
                    'per_page' => $locations->perPage(),
                    'total' => $locations->total(),
                    'last_page' => $locations->lastPage(),
                    'from' => $locations->firstItem(),
                    'to' => $locations->lastItem(),
                ],
            ];
        }

        // Sin filtros, devolver todas las locations
        return $query->get()->toArray();
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

    public function findByOrigin(string $origin): array
    {
        return Location::where('origin', $origin)
            ->orderBy('name')
            ->get()
            ->toArray();
    }
}
