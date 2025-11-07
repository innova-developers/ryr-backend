<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Locations\Application\DTOs\CreateLocationDTO;
use App\Contexts\Locations\Application\DTOs\UpdateLocationDTO;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Customer;
use App\Shared\Models\Location;
use Illuminate\Support\Facades\DB;

class UpdateCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository,
        private readonly LocationsRepository $locationsRepository
    ) {
    }
    
    public function __invoke(UpdateCustomerDTO $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            // Obtener el cliente actual antes de actualizar para comparar nombres
            $currentCustomer = $this->repository->findById($dto->id);
            $oldFullName = "{$currentCustomer->name} {$currentCustomer->last_name}";
            
            // Actualizar el cliente
            $customer = $this->repository->update($dto);
            
            $newFullName = "{$customer->name} {$customer->last_name}";
            
            // Buscar Location por el nombre antiguo o nuevo
            $location = Location::where('name', $oldFullName)
                ->orWhere('name', $newFullName)
                ->first();
            
            // Si el cliente tiene dirección, actualizar o crear la Location
            if ($dto->address) {
                if ($location) {
                    // Actualizar Location existente
                    $this->updateCustomerLocation($location->id, $customer, $dto);
                } else {
                    // Crear nueva Location
                    $this->createCustomerLocation($customer, $dto);
                }
            } elseif ($location) {
                // Si el cliente ya no tiene dirección pero existe la Location,
                // actualizamos el nombre por si cambió, pero mantenemos la dirección existente
                $updateDTO = new UpdateLocationDTO(
                    id: $location->id,
                    name: $newFullName,
                    address: $location->address,
                    origin: $location->origin,
                    phone: $dto->phone ?? $dto->mobile ?? $location->phone ?? 'Sin teléfono',
                    map: $dto->mapsUrl ?? $location->map,
                    schedule: $dto->businessHours ?? $location->schedule ?? 'Sin horario especificado',
                    observation: $dto->observations ?? $location->observation
                );
                $this->locationsRepository->update($updateDTO);
            }
            
            return $customer;
        });
    }
    
    private function createCustomerLocation(Customer $customer, UpdateCustomerDTO $dto): void
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
    
    private function updateCustomerLocation(int $locationId, Customer $customer, UpdateCustomerDTO $dto): void
    {
        $fullName = "{$customer->name} {$customer->last_name}";
        $address = $dto->address;
        $origin = $dto->city ?? 'Sin especificar';
        $phone = $dto->phone ?? $dto->mobile ?? 'Sin teléfono';
        $map = $dto->mapsUrl;
        $schedule = $dto->businessHours ?? 'Sin horario especificado';
        $observation = $dto->observations;
        
        $updateDTO = new UpdateLocationDTO(
            id: $locationId,
            name: $fullName,
            address: $address,
            origin: $origin,
            phone: $phone,
            map: $map,
            schedule: $schedule,
            observation: $observation
        );
        
        $this->locationsRepository->update($updateDTO);
    }
}
