<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;

class DeleteDestinationUseCase
{
    private DestinationRepository $repository;

    public function __construct(DestinationRepository $repository)
    {
        $this->repository = $repository;
    }
    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
