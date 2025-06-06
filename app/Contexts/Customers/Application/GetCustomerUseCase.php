<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Models\Customer;

class GetCustomerUseCase
{
    private CustomerRepository $repository;

    public function __construct(CustomerRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Customer
    {
        return $this->repository->findById($id);
    }
}
