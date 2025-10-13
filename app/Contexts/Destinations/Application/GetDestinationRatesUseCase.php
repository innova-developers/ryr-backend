<?php

namespace App\Contexts\Destinations\Application;

use App\Contexts\Destinations\Application\DTO\GetDestinationRatesDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Models\Destination;

class GetDestinationRatesUseCase
{
    public function __construct(
        private readonly DestinationRepository $repository
    ) {
    }

    public function __invoke(GetDestinationRatesDTO $dto): Destination
    {
        return $this->repository->getRatesByOriginAndDestination($dto);
    }

}
