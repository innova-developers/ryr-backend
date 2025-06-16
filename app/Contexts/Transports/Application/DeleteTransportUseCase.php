<?php

namespace App\Contexts\Transports\Application;

use App\Contexts\Transports\Domain\Repositories\TransportRepository;

class DeleteTransportUseCase
{
    public function __construct(
        private readonly TransportRepository $repository
    ) {
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }

}
