<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Domain\Repositories\CustomerRepository;

class DeleteCustomerUseCase
{
    public function __construct(
        private readonly CustomerRepository $repository
    ) {
    }
    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
