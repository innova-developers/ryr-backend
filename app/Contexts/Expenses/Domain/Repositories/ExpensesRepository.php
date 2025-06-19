<?php

namespace App\Contexts\Expenses\Domain\Repositories;

use App\Contexts\Expenses\Application\DTOs\CreateExpenseDTO;
use App\Contexts\Expenses\Application\DTOs\UpdateExpenseDTO;
use App\Shared\Models\Expense;

interface ExpensesRepository
{
    public function findByTransportId(int $transportId): array;
    public function findById(int $id): Expense;
    public function create(CreateExpenseDTO $dto): Expense;
    public function update(UpdateExpenseDTO $dto): Expense;
    public function delete(int $id): void;
}
