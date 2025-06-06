<?php

namespace Tests\Unit\Customers;

use App\Contexts\Customers\Application\CreateCustomerUseCase;
use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Models\Customer;
use Mockery;
use Tests\TestCase;

class CreateCustomerUseCaseTest extends TestCase
{
    public function test_can_create_customer(): void
    {
        $repository = Mockery::mock(CustomerRepository::class);
        $useCase = new CreateCustomerUseCase($repository);

        $dto = new CreateCustomerDTO(
            '12345678',
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
            true,
            1,
            1 // branch_id
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
