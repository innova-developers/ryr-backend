<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Shared\Models\Customer;

class GetCustomersUseCase
{
    private CustomerRepository $repository;

    public function __construct(CustomerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return Customer[]
     */
    public function __invoke(): array
    {
        return $this->repository->get();
    }
}
