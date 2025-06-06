<?php

namespace App\Contexts\Customers\Domain\Repositories;

use App\Contexts\Customers\Application\DTO\CreateCustomerDTO;
use App\Contexts\Customers\Application\DTO\UpdateCustomerDTO;
use App\Shared\Models\Customer;

interface CustomerRepository
{
    public function get(): array;
    public function create(CreateCustomerDTO $dto): Customer;
    public function findById(int $id): ?Customer;
    public function update(UpdateCustomerDTO $dto): Customer;
    public function delete(int $id): void;

}
