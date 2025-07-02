<?php

namespace App\Contexts\Incomes\Application;

use App\Contexts\Incomes\Application\DTOs\UpdateIncomeDTO;
use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;

class UpdateIncomeUseCase
{
    public function __construct(
        private readonly IncomesRepository $repository
    ) {
    }

    public function __invoke(UpdateIncomeDTO $dto): array
    {
        $income = $this->repository->update($dto);

        return [
            'id' => $income->id,
            'income_category_id' => $income->income_category_id,
            'date' => $income->date->format('Y-m-d'),
            'detail' => $income->detail,
            'amount' => $income->amount,
        ];
    }
}
