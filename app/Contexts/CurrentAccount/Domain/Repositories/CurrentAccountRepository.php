<?php

namespace App\Contexts\CurrentAccount\Domain\Repositories;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\DTO\CurrentAccountFilterDTO;
use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Shared\Models\CurrentAccount;
use Illuminate\Pagination\LengthAwarePaginator;

interface CurrentAccountRepository
{
    public function create(CreateCurrentAccountDTO $dto): CurrentAccount;
    public function update(UpdateCurrentAccountDTO $dto): CurrentAccount;
    public function delete(int $id): bool;
    public function findById(int $id): ?CurrentAccount;
    public function findByCustomerId(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator;
    public function getCustomerBalance(int $customerId): float;
    public function getCustomerOperationalBalance(int $customerId): float;
    public function getCustomerTransactions(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator;
    public function confirmTransaction(int $id): CurrentAccount;
}
