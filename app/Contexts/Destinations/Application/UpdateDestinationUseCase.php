<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Application\DTO\UpdateDestinationDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Models\Destination;

class UpdateDestinationUseCase
{
    public function __construct(
        private readonly DestinationRepository $repository
    ) {
    }
    public function __invoke(UpdateDestinationDTO $dto): Destination
    {
        return $this->repository->update($dto);
    }
}
