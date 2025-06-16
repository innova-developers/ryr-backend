<?php

namespace App\Contexts\Transports\Domain\Repositories;

use App\Contexts\Transports\Application\DTOs\CreateTransportDTO;
use App\Contexts\Transports\Application\DTOs\UpdateTransportDTO;
use App\Shared\Models\Transport;

interface TransportRepository
{
    public function findAll(): array;
    public function findById(int $id): Transport;
    public function findByPlate(string $plate): Transport;
    public function save(CreateTransportDTO $dto): Transport;
    public function delete(int $id): void;

    public function update(UpdateTransportDTO $dto): Transport;
}
