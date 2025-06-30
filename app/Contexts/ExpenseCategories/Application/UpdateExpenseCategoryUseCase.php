<?php

namespace App\Contexts\ExpenseCategories\Application;

use App\Contexts\ExpenseCategories\Application\DTO\UpdateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Shared\Models\ExpenseCategory;

class UpdateExpenseCategoryUseCase
{
    public function __construct(
        private readonly ExpenseCategoryRepository $repository
    ) {
    }

    public function execute(int $id, UpdateExpenseCategoryDTO $dto): ExpenseCategory
    {
        return $this->repository->update($id, $dto);
    }
} 