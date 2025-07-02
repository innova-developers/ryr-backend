<?php

namespace App\Contexts\Incomes\Application;

use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;

class DeleteIncomeUseCase
{
    public function __construct(
        private readonly IncomesRepository $repository
    ) {
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
