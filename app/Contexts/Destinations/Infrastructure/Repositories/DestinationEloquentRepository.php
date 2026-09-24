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
     * RC-550: el filtro "Destino" del listado de comisiones y del panel de cobradores
     * bajaba el listado completo (25.899 filas, 6 MB, 3,8 s y 71 MB en prod) para
     * armar un <select>. Esto devuelve sólo lo que el select muestra, filtrado en la
     * base y con tope de filas.
     *
     * Cada palabra del texto tiene que aparecer en el origen o en el destino, así
     * "rosario funes" o "ROSARIO - FUNES" encuentran la ruta en cualquiera de los
     * dos sentidos. Los destinos dados de baja quedan afuera, igual que en get().
     * Se toman hasta 5 palabras: alcanza para cualquier ruta y acota la consulta.
     *
     * Primero van las rutas cuyo origen empieza con la primera palabra y después las
     * que la tienen al principio del destino: con "rosario" hay más de 50 coincidencias
     * (p. ej. "BARRIO FISHERTON ROSARIO") y sin este orden las de ROSARIO quedaban afuera.
     *
     * Lo que escribe el usuario se busca como texto literal: "%" y "_" no son comodines
     * (sin escaparlos, "%" devolvía 50 rutas cualesquiera, como "- - ---").
     */
    public function search(string $term, int $limit): array
    {
        $words = array_slice(
            array_values(array_filter(preg_split('/[\s\-]+/u', trim($term)) ?: [], 'strlen')),
            0,
            5
        );

        $query = Destination::query()->select(['id', 'origin', 'destination']);

        foreach ($words as $word) {
            $like = '%' . $this->escapeLike($word) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw("origin LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("destination LIKE ? ESCAPE '!'", [$like]);
            });
        }

        if ($words !== []) {
            $prefix = $this->escapeLike($words[0]) . '%';
            $query->orderByRaw(
                "CASE WHEN origin LIKE ? ESCAPE '!' THEN 0 WHEN destination LIKE ? ESCAPE '!' THEN 1 ELSE 2 END",
                [$prefix, $prefix]
            );
        }

        return $query
            ->orderBy('origin')
            ->orderBy('destination')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Escapa los comodines de LIKE con "!" y no con la barra invertida: MySQL y sqlite
     * (tests) no tratan igual la barra en los literales, "!" no tiene ese problema y
     * con ESCAPE explícito se comporta igual en los dos.
     */
    private function escapeLike(string $text): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
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
