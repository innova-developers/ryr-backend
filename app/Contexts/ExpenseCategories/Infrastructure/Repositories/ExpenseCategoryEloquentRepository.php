<?php

namespace App\Contexts\ExpenseCategories\Infrastructure\Repositories;

use App\Contexts\ExpenseCategories\Application\DTO\CreateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Application\DTO\UpdateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Shared\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryEloquentRepository implements ExpenseCategoryRepository
{
    public function create(CreateExpenseCategoryDTO $dto): ExpenseCategory
    {
        return ExpenseCategory::create([
            'name' => $dto->name,
            'description' => $dto->description,
            'is_active' => $dto->isActive,
        ]);
    }

    public function update(int $id, UpdateExpenseCategoryDTO $dto): ExpenseCategory
    {
        $category = $this->findById($id);

        if (! $category) {
            throw new \Exception('Categoría no encontrada');
        }

        $category->update([
            'name' => $dto->name,
            'description' => $dto->description,
            'is_active' => $dto->isActive,
        ]);

        return $category->fresh();
    }

    public function delete(int $id): bool
    {
        $category = $this->findById($id);

        if (! $category) {
            return false;
        }

        return $category->delete();
    }

    public function findById(int $id): ?ExpenseCategory
    {
        return ExpenseCategory::find($id);
    }

    public function findAll(): Collection
    {
        return ExpenseCategory::all();
    }

    public function findActive(): Collection
    {
        return ExpenseCategory::where('is_active', true)->get();
    }
}
