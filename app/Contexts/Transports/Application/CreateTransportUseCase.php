<?php

namespace App\Contexts\Transports\Application;

use App\Contexts\Transports\Application\DTOs\CreateTransportDTO;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Shared\Models\Transport;

class CreateTransportUseCase
{
    public function __construct(
        private readonly TransportRepository $repository
    ) {
    }

    public function __invoke(CreateTransportDTO $dto): Transport
    {
        return $this->repository->save($dto);
    }
}
