<?php

namespace App\Contexts\Incomes\Application;

use App\Contexts\Incomes\Application\DTOs\CreateIncomeDTO;
use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;

class CreateIncomeUseCase
{
    public function __construct(
        private readonly IncomesRepository $repository
    ) {
    }

    public function __invoke(CreateIncomeDTO $dto): array
    {
        $income = $this->repository->create($dto);

        return [
            'id' => $income->id,
            'income_category_id' => $income->income_category_id,
            'date' => $income->date->format('Y-m-d'),
            'detail' => $income->detail,
            'amount' => $income->amount,
        ];
    }
}
