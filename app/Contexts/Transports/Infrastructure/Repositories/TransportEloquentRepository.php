<?php

namespace App\Contexts\Transports\Infrastructure\Repositories;

use App\Contexts\Transports\Application\DTOs\CreateTransportDTO;
use App\Contexts\Transports\Application\DTOs\GetTransportsFiltersDTO;
use App\Contexts\Transports\Application\DTOs\UpdateTransportDTO;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Shared\Models\Transport;

class TransportEloquentRepository implements TransportRepository
{
    public function findAll(?GetTransportsFiltersDTO $filters = null): array
    {
        $query = Transport::query();

        // Aplicar filtro de búsqueda
        if ($filters && $filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('plate', 'like', '%' . $filters->search . '%')
                  ->orWhere('description', 'like', '%' . $filters->search . '%')
                  ->orWhere('phone', 'like', '%' . $filters->search . '%');
            });
        }

        // Aplicar ordenamiento
        if ($filters && $filters->sortBy) {
            $allowedSortFields = ['plate', 'description', 'phone', 'created_at'];
            if (in_array($filters->sortBy, $allowedSortFields)) {
                $query->orderBy($filters->sortBy, $filters->sortDirection);
            }
        } else {
            $query->orderBy('plate', 'asc');
        }

        // Aplicar paginación
        if ($filters) {
            $perPage = min($filters->perPage, 100); // Limitar a máximo 100 por página
            $transports = $query->paginate($perPage, ['*'], 'page', $filters->page);

            return [
                'data' => $transports->items(),
                'pagination' => [
                    'current_page' => $transports->currentPage(),
                    'per_page' => $transports->perPage(),
                    'total' => $transports->total(),
                    'last_page' => $transports->lastPage(),
                    'from' => $transports->firstItem(),
                    'to' => $transports->lastItem(),
                ],
            ];
        }

        // Sin filtros, devolver todos los transports
        return $query->get()->toArray();
    }

    public function findById(int $id): Transport
    {
        try {
            return Transport::find($id);
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Transporte {$id} no encontrado.");
        }
    }

    public function findByPlate(string $plate): Transport
    {
        try {
            return Transport::where('plate', $plate)->first();
        } catch (\Throwable) {
            throw new \InvalidArgumentException("Transporte {$id} no encontrado.");
        }
    }

    public function save(CreateTransportDTO $dto): Transport
    {
        try {
            $transport = new Transport();
            $transport->plate = $dto->plate;
            $transport->description = $dto->description;
            $transport->phone = $dto->phone;
            $transport->insurance = $dto->insurance;
            $transport->usage = $dto->usage;
            $transport->observation = $dto->observation;
            $transport->save();

            return $transport;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Error al guardar el transporte .". $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        try {
            $transport = Transport::find($id);
            $transport->delete();
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Error al eliminar el transporte {$id}: " . $e->getMessage());
        }
    }

    public function update(UpdateTransportDTO $dto): Transport
    {
        try {
            $transport = Transport::find($dto->id);
            $transport->plate = $dto->plate;
            $transport->description = $dto->description;
            $transport->phone = $dto->phone;
            $transport->insurance = $dto->insurance;
            $transport->usage = $dto->usage;
            $transport->observation = $dto->observation;
            $transport->save();

            return $transport;
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Error al actualizar el transporte {$dto->id}: " . $e->getMessage());
        }
    }
}
