<?php

namespace App\Contexts\IncomeCategories\Application;

use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;

class DeleteIncomeCategoryUseCase
{
    public function __construct(
        private readonly IncomeCategoriesRepository $repository
    ) {
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
