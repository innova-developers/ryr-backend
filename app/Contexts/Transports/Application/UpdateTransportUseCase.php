<?php

namespace App\Contexts\Transports\Application;

use App\Contexts\Transports\Application\DTOs\UpdateTransportDTO;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;
use App\Shared\Models\Transport;

class UpdateTransportUseCase
{
    public function __construct(
        private readonly TransportRepository $repository
    ) {
    }

    public function __invoke(UpdateTransportDTO $dto): Transport
    {
        return $this->repository->update($dto);
    }

}
