<?php

namespace App\Contexts\Incomes\Domain\Repositories;

use App\Contexts\Incomes\Application\DTOs\CreateIncomeDTO;
use App\Contexts\Incomes\Application\DTOs\IncomeFilterDTO;
use App\Contexts\Incomes\Application\DTOs\UpdateIncomeDTO;
use App\Shared\Models\Income;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IncomesRepository
{
    public function findById(int $id): Income;
    public function findAll(IncomeFilterDTO $filterDTO): LengthAwarePaginator;
    public function create(CreateIncomeDTO $dto): Income;
    public function update(UpdateIncomeDTO $dto): Income;
    public function delete(int $id): void;
}
