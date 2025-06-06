<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Models\Destination;

class CreateDestinationUseCase
{
    public function __construct(
        private readonly DestinationRepository $repository
    ) {
    }
    public function __invoke(CreateDestinationDTO $dto): Destination
    {
        return $this->repository->create($dto);
    }
}
