<?php

namespace App\Contexts\Transports\Application;

use App\Contexts\Transports\Domain\Repositories\TransportRepository;

class ListTransportsUseCase
{
    public function __construct(
        private readonly TransportRepository $repository
    ) {
    }

    public function __invoke(): array
    {
        return $this->repository->findAll();
    }
}
