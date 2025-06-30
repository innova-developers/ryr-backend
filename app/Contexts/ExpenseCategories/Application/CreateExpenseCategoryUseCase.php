<?php

namespace App\Contexts\ExpenseCategories\Application;

use App\Contexts\ExpenseCategories\Application\DTO\CreateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Domain\Repositories\ExpenseCategoryRepository;
use App\Shared\Models\ExpenseCategory;

class CreateExpenseCategoryUseCase
{
    public function __construct(
        private readonly ExpenseCategoryRepository $repository
    ) {
    }

    public function execute(CreateExpenseCategoryDTO $dto): ExpenseCategory
    {
        return $this->repository->create($dto);
    }
} 