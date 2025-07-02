<?php

namespace App\Contexts\ExpenseCategories\Application;

use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Shared\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class GetExpenseCategoriesUseCase
{
    public function __construct(
        private readonly ExpenseCategoryRepository $repository
    ) {
    }

    public function execute(): Collection
    {
        return $this->repository->findAll();
    }

    public function executeActive(): Collection
    {
        return $this->repository->findActive();
    }

    public function executeById(int $id): ?ExpenseCategory
    {
        return $this->repository->findById($id);
    }
}
