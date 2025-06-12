<?php

namespace App\Contexts\Customers\Application;

use App\Contexts\Customers\Domain\Repositories\CustomerRepository;

readonly class SearchCustomersUseCase
{
    public function __construct(
        private CustomerRepository $repository
    ) {
    }

    public function __invoke(string $query): array
    {
        return $this->repository->search($query);
    }
}
