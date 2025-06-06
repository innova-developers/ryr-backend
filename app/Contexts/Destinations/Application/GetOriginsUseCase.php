<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Models\Destination;

class GetOriginsUseCase
{
    private DestinationRepository $repository;

    public function __construct(DestinationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return Destination[]
     */
    public function __invoke(): array
    {
        return $this->repository->getOrigins();
    }
}
