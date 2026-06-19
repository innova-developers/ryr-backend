<?php

namespace Tests\Unit\Customers;

use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Application\UpdateCustomerUseCase;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Customer;
use Mockery;
use Tests\TestCase;

class UpdateCustomerUseCaseTest extends TestCase
{
    public function test_can_update_customer(): void
    {
        $repository = Mockery::mock(CustomerRepository::class);
        // El use case ahora requiere también los repositorios de Locations y Destinations
        $locationsRepository = Mockery::mock(LocationsRepository::class);
        $destinationRepository = Mockery::mock(DestinationRepository::class);
        $locationsRepository->shouldReceive('create')->andReturn(Mockery::mock(\App\Shared\Models\Location::class));
        $destinationRepository->shouldReceive('findByOriginAndDestination')->andReturn(Mockery::mock(\App\Shared\Models\Destination::class));
        $destinationRepository->shouldReceive('create')->andReturn(Mockery::mock(\App\Shared\Models\Destination::class));
        $useCase = new UpdateCustomerUseCase($repository, $locationsRepository, $destinationRepository);

        $dto = new UpdateCustomerDTO(
            1,
            '87654321',
            '20876543210',
            'Jane',
            'Smith',
            '9876543210',
            'jane@example.com',
            '456 Oak St',
            'Los Angeles',
            '1234567890',
            'https://maps.google.com/updated',
            '10-19',
            'Updated customer',
            true, // is_premium
            true, // auto_calculate_iva
            1,    // user_id
            1     // branch_id
        );

        $expectedCustomer = new Customer();
        $expectedCustomer->id = 1;
        $expectedCustomer->dni = '87654321';
        $expectedCustomer->name = 'Jane';
        $expectedCustomer->last_name = 'Smith';
        $expectedCustomer->email = 'jane@example.com';
        $expectedCustomer->is_premium = true;

        $repository->shouldReceive('update')
            ->once()
            ->with(Mockery::type(UpdateCustomerDTO::class))
            ->andReturn($expectedCustomer);

        $result = $useCase($dto);

        $this->assertEquals($expectedCustomer, $result);
    }
}
