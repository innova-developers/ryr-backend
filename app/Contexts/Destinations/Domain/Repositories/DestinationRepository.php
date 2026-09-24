<?php

namespace App\Contexts\Destinations\Domain\Repositories;

use App\Contexts\Destinations\Application\DTO\BulkAdjustPricesDTO;
use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Application\DTO\GetDestinationRatesDTO;
use App\Contexts\Destinations\Application\DTO\UpdateDestinationDTO;
use App\Shared\Models\Destination;

interface DestinationRepository
{
    public function get(): array;

    /**
     * Versión liviana y acotada del listado para selects con búsqueda:
     * sólo id, origin y destination, como mucho $limit filas.
     *
     * @return array<int, array{id: int, origin: string, destination: string}>
     */
    public function search(string $term, int $limit): array;

    public function create(CreateDestinationDTO $dto): Destination;
    public function findById(int $id): Destination;
    public function update(UpdateDestinationDTO $dto): Destination;
    public function bulkAdjustPrices(BulkAdjustPricesDTO $dto): int;
    public function delete(int $id): void;
    public function getOrigins(): array;
    public function getDestinationsByOrigin(string $origin): array;
    public function getRatesByOriginAndDestination(GetDestinationRatesDTO $dto): Destination;
    public function findByOriginAndDestination(string $origin, string $destination): Destination;
}
