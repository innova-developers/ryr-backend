<?php

namespace App\Contexts\Expenses\Application;

use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;

class GetExpensesByTransportUseCase
{
    public function __construct(
        private readonly ExpensesRepository $repository
    ) {
    }

    public function __invoke(int $transportId): array
    {
        return $this->repository->findByTransportId($transportId);
    }
}
