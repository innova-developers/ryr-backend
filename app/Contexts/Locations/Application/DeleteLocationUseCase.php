<?php

namespace App\Contexts\Locations\Application;

use App\Contexts\Locations\Domain\Repositories\LocationsRepository;

class DeleteLocationUseCase
{
    public function __construct(
        private readonly LocationsRepository $repository
    ) {
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
