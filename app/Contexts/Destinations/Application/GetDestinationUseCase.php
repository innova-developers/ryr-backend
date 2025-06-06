<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Models\Destination;

class GetDestinationUseCase
{
    public function __construct(
        private readonly DestinationRepository $repository
    ){}
    public function __invoke(int $id): Destination
    {
        return $this->repository->findById($id);
    }
}
