<?php

namespace App\Contexts\Transports\Application;

use App\Contexts\Transports\Application\DTOs\GetTransportsFiltersDTO;
use App\Contexts\Transports\Domain\Repositories\TransportRepository;

class ListTransportsUseCase
{
    public function __construct(
        private readonly TransportRepository $repository
    ) {
    }

    public function __invoke(?GetTransportsFiltersDTO $filters = null): array
    {
        return $this->repository->findAll($filters);
    }
}
