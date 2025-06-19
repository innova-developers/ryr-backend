<?php

namespace App\Contexts\Expenses\Application;

use App\Contexts\Expenses\Application\DTOs\CreateExpenseDTO;
use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;

class CreateExpenseUseCase
{
    public function __construct(
        private readonly ExpensesRepository $repository
    ) {
    }

    public function __invoke(CreateExpenseDTO $dto): array
    {
        $expense = $this->repository->create($dto);

        return [
            'id' => $expense->id,
            'transport_id' => $expense->transport_id,
            'date' => $expense->date->format('Y-m-d'),
            'detail' => $expense->detail,
            'amount' => $expense->amount,
            'created_at' => $expense->created_at,
            'updated_at' => $expense->updated_at,
        ];
    }
}
