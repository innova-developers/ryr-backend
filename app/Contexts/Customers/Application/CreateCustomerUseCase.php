<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Customer;
use Illuminate\Support\Facades\DB;

class CreateCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository,
        private readonly LocationsRepository $locationsRepository
    ) {
    }
    
    public function __invoke(CreateCustomerDTO $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            // Crear el cliente
            $customer = $this->repository->create($dto);
            
            // Si el cliente tiene dirección, crear una Location automáticamente
            if ($dto->address) {
                $this->createCustomerLocation($customer, $dto);
            }
            
            return $customer;
        });
    }
    
    private function createCustomerLocation(Customer $customer, CreateCustomerDTO $dto): void
    {
        $fullName = "{$customer->name} {$customer->last_name}";
        $address = $dto->address;
        $origin = $dto->city ?? 'Sin especificar';
        $phone = $dto->phone ?? $dto->mobile ?? 'Sin teléfono';
        $map = $dto->mapsUrl;
        $schedule = $dto->businessHours ?? 'Sin horario especificado';
        $observation = $dto->observations;
        
        $locationDTO = new CreateLocationDTO(
            name: $fullName,
            address: $address,
            origin: $origin,
            phone: $phone,
            map: $map,
            schedule: $schedule,
            observation: $observation
        );
        
        $this->locationsRepository->create($locationDTO);
    }
}
