<?php

namespace App\Contexts\Expenses\Application;

use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;

class DeleteExpenseUseCase
{
    public function __construct(
        private readonly ExpensesRepository $repository
    ) {
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
