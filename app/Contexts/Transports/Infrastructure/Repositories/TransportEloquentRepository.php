<?php

namespace App\Contexts\Transports\Infrastructure\Repositories;

use App\Contexts\Transports\Application\DTOs\CreateTransportDTO;
use App\Contexts\Transports\Application\DTOs\UpdateTransportDTO;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Shared\Models\Transport;

class TransportEloquentRepository implements TransportRepository
{
    public function findAll(): array
    {
        return Transport::all()->toArray();
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
