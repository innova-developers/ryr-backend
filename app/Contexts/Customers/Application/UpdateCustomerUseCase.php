<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Models\Customer;

class UpdateCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository
    ) {
    }
    public function __invoke(UpdateCustomerDTO $dto): Customer
    {
        return $this->repository->update($dto);
    }
}
