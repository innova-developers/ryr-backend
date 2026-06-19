<?php

namespace Tests\Unit\Customers;

use App\Contexts\Customers\Application\CreateCustomerUseCase;
use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Locations\Domain\Repositories\LocationsRepository;
use App\Shared\Models\Customer;
use Mockery;
use Tests\TestCase;

class CreateCustomerUseCaseTest extends TestCase
{
    public function test_can_create_customer(): void
    {
        $repository = Mockery::mock(CustomerRepository::class);
        // El use case ahora requiere también los repositorios de Locations y Destinations
        $locationsRepository = Mockery::mock(LocationsRepository::class);
        $destinationRepository = Mockery::mock(DestinationRepository::class);
        $locationsRepository->shouldReceive('create')->andReturn(Mockery::mock(\App\Shared\Models\Location::class));
        $destinationRepository->shouldReceive('findByOriginAndDestination')->andReturn(Mockery::mock(\App\Shared\Models\Destination::class));
        $destinationRepository->shouldReceive('create')->andReturn(Mockery::mock(\App\Shared\Models\Destination::class));
        $useCase = new CreateCustomerUseCase($repository, $locationsRepository, $destinationRepository);

        $dto = new CreateCustomerDTO(
            '12345678',
            '20123456780',
            'John',
            'Doe',
            '1234567890',
            'john@example.com',
            '123 Main St',
            'New York',
            '0987654321',
            'https://maps.google.com',
            '9-18',
            'Test customer',
            true, // is_premium
            true, // auto_calculate_iva
            1,    // user_id
            1     // branch_id
        );

        $expectedCustomer = new Customer();
        $expectedCustomer->id = 1;
        $expectedCustomer->dni = '12345678';
        $expectedCustomer->name = 'John';
        $expectedCustomer->last_name = 'Doe';
        $expectedCustomer->email = 'john@example.com';
        $expectedCustomer->is_premium = true;

        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::type(CreateCustomerDTO::class))
            ->andReturn($expectedCustomer);

        $result = $useCase($dto);

        $this->assertEquals($expectedCustomer, $result);
    }
}
