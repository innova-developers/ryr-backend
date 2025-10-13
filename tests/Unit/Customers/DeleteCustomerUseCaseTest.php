<?php

namespace Tests\Unit\Customers;

use App\Contexts\Customers\Application\DeleteCustomerUseCase;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use Mockery;
use Tests\TestCase;

class DeleteCustomerUseCaseTest extends TestCase
{
    public function test_can_delete_customer(): void
    {
        $repository = Mockery::mock(CustomerRepository::class);
        $useCase = new DeleteCustomerUseCase($repository);

        $repository->shouldReceive('delete')
            ->once()
            ->with(1);

        $useCase(1);
    }
}
