<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Application\DTO\CreateDestinationDTO;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Customer;

class UpdateCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository,
        private readonly LocationsRepository $locationsRepository,
        private readonly DestinationRepository $destinationRepository
    ) {
    }

    public function __invoke(UpdateCustomerDTO $dto): Customer
    {
        $customer = $this->repository->update($dto);

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

        // Crear Destination con la ciudad del cliente si hay información de ciudad
        if ($dto->city) {
            try {
                // Verificar si ya existe una Destination con origin=city y destination=city
                $this->destinationRepository->findByOriginAndDestination($dto->city, $dto->city);
            } catch (\Exception $e) {
                // Si no existe, crearla
                $destinationDTO = new CreateDestinationDTO(
                    origin: $dto->city,
                    destination: $dto->city,
                    fixed_price: 0.0,
                    small_bulk_price: 0.0,
                    large_bulk_price: 0.0
                );

                $this->destinationRepository->create($destinationDTO);
            }
        }

        return $customer;
    }
}
