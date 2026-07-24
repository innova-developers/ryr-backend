<?php

namespace App\Contexts\Destinations\Infrastructure\Repositories;

use App\Contexts\Destinations\Application\DTO\BulkAdjustPricesDTO;
use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Application\DTO\GetDestinationRatesDTO;
use App\Contexts\Destinations\Application\DTO\UpdateDestinationDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Models\Destination;
use Illuminate\Support\Facades\DB;

class DestinationEloquentRepository implements DestinationRepository
{
    public function get(): array
    {
        return Destination::all()->toArray();
    }

    /**
     * @throws \Exception
     */
    public function create(CreateDestinationDTO $dto): Destination
    {
        try {
            $oneWayDestination = new Destination();
            $oneWayDestination->origin = $dto->origin;
            $oneWayDestination->destination = $dto->destination;
            $oneWayDestination->fixed_price = $dto->fixed_price;
            $oneWayDestination->small_bulk_price = $dto->small_bulk_price;
            $oneWayDestination->large_bulk_price = $dto->large_bulk_price;
            $oneWayDestination->save();
            $roundTripDestination = new Destination();
            $roundTripDestination->origin = $dto->destination;
            $roundTripDestination->destination = $dto->origin;
            $roundTripDestination->fixed_price = $dto->fixed_price;
            $roundTripDestination->small_bulk_price = $dto->small_bulk_price;
            $roundTripDestination->large_bulk_price = $dto->large_bulk_price;
            $roundTripDestination->save();

            return $oneWayDestination;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function findById(int $id): Destination
    {
        try {
            $destination = Destination::find($id);

            if (! $destination) {
                throw new \Exception('Destino no encontrado');
            }

            return $destination;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    public function update(UpdateDestinationDTO $dto): Destination
    {
        try {
            $destination = Destination::findOrFail($dto->id);
            $destination->origin = $dto->origin;
            $destination->destination = $dto->destination;
            $destination->fixed_price = $dto->fixed_price;
            $destination->small_bulk_price = $dto->small_bulk_price;
            $destination->large_bulk_price = $dto->large_bulk_price;
            $destination->save();

            return $destination;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }

    /**
     * Ajusta en bloque los precios de todos los destinos por un porcentaje.
     * Solo actualiza las columnas marcadas en el DTO. Redondea a 2 decimales.
     *
     * @throws \Exception
     */
    public function bulkAdjustPrices(BulkAdjustPricesDTO $dto): int
    {
        $factor = 1 + ($dto->percentage / 100);

        $columns = [];
        if ($dto->fixed_price) {
            $columns[] = 'fixed_price';
        }
        if ($dto->small_bulk_price) {
            $columns[] = 'small_bulk_price';
        }
        if ($dto->large_bulk_price) {
            $columns[] = 'large_bulk_price';
        }

        if (empty($columns)) {
            return 0;
        }

        $updates = [];
        foreach ($columns as $column) {
            $updates[$column] = DB::raw('ROUND(`' . $column . '` * ' . $factor . ', 2)');
        }
        $updates['updated_at'] = now();

        try {
            return Destination::query()
                ->whereNull('deleted_at')
                ->update($updates);
        } catch (\Exception $exception) {
            throw new \Exception('Error al ajustar precios: ' . $exception->getMessage());
        }
    }

    public function delete(int $id): void
    {
        try {
            $destination = Destination::findOrFail($id);
            $destination->delete();
        } catch (\Exception $exception) {
            throw new \RuntimeException('Error al eliminar Destino: ' . $exception->getMessage(), 0, $exception);
        }
    }

    public function getOrigins(): array
    {
        return Destination::select('origin')
            ->distinct()
            ->orderBy('origin')
            ->pluck('origin')
            ->toArray();
    }

    public function getDestinationsByOrigin(string $origin): array
    {
        return Destination::select('destination')
            ->where('origin', $origin)
            ->distinct()
            ->orderBy('destination')
            ->pluck('destination')
            ->toArray();
    }

    /**
     * @throws \Exception
     */
    public function getRatesByOriginAndDestination(GetDestinationRatesDTO $dto): Destination
    {
        try {
            return Destination::where('origin', $dto->origin)
                ->where('destination', $dto->destination)
                ->select('id', 'fixed_price', 'small_bulk_price', 'large_bulk_price')
                ->first();
        } catch (\Exception $exception) {
            throw new \Exception('Error al obtener tarifas: ' . $exception->getMessage());
        }
    }
    public function findByOriginAndDestination(string $origin, string $destination): Destination
    {
        try {
            return Destination::where('origin', $origin)
                ->where('destination', $destination)
                ->firstOrFail();
        } catch (\Exception $exception) {
            throw new \Exception('Destino no encontrado: ' . $exception->getMessage());
        }
    }
}
