<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Customer;

class CreateCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository,
        private readonly LocationsRepository $locationsRepository
    ) {
    }

    public function __invoke(CreateCustomerDTO $dto): Customer
    {
        $customer = $this->repository->create($dto);

        // Crear Location con la dirección del cliente si hay información de dirección
        if ($dto->address) {
            $locationDTO = new CreateLocationDTO(
                name: "{$dto->name} {$dto->lastName}",
                address: $dto->address,
                origin: $dto->city ?? '',
                phone: $dto->phone ?? '',
                map: $dto->mapsUrl,
                schedule: $dto->businessHours ?? '',
                observation: $dto->observations
            );

            $this->locationsRepository->create($locationDTO);
        }

        return $customer;
    }
}
