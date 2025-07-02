<?php

namespace App\Contexts\ExpenseCategories\Domain\Repositories;

use App\Contexts\ExpenseCategories\Application\DTO\CreateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Application\DTO\UpdateExpenseCategoryDTO;
use App\Shared\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

interface ExpenseCategoryRepository
{
    public function create(CreateExpenseCategoryDTO $dto): ExpenseCategory;
    public function update(int $id, UpdateExpenseCategoryDTO $dto): ExpenseCategory;
    public function delete(int $id): bool;
    public function findById(int $id): ?ExpenseCategory;
    public function findAll(): Collection;
    public function findActive(): Collection;
}
