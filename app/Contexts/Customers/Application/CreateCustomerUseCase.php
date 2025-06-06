<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Models\Customer;

class CreateCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository
    ) {
    }
    public function __invoke(CreateCustomerDTO $dto): Customer
    {
        return $this->repository->create($dto);
    }
}
