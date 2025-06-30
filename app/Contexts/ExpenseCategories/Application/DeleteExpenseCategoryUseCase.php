<?php

namespace App\Contexts\ExpenseCategories\Application;

use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;

class DeleteExpenseCategoryUseCase
{
    public function __construct(
        private readonly ExpenseCategoryRepository $repository
    ) {
    }

    public function execute(int $id): bool
    {
        return $this->repository->delete($id);
    }
} 