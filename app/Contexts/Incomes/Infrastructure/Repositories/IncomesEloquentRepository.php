<?php

namespace App\Contexts\Incomes\Infrastructure\Repositories;

use App\Contexts\Incomes\Application\DTOs\CreateIncomeDTO;
use App\Contexts\Incomes\Application\DTOs\IncomeFilterDTO;
use App\Contexts\Incomes\Application\DTOs\UpdateIncomeDTO;
use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;
use App\Shared\Models\Income;
use Illuminate\Database\Eloquent\Collection;

class IncomesEloquentRepository implements IncomesRepository
{
    public function findById(int $id): Income
    {
        $income = Income::with(['category', 'user'])->find($id);

        if (! $income) {
            throw new \Exception('Ingreso no encontrado');
        }

        return $income;
    }

    public function findAll(IncomeFilterDTO $filterDTO): Collection
    {
        $query = Income::with(['category', 'user'])
            ->orderBy('date', 'desc')
            ->when($filterDTO->dateFrom, fn ($q) => $q->where('date', '>=', $filterDTO->dateFrom))
            ->when($filterDTO->dateTo, fn ($q) => $q->where('date', '<=', $filterDTO->dateTo))
            ->when($filterDTO->categoryId, fn ($q) => $q->where('income_category_id', $filterDTO->categoryId))
            ->when($filterDTO->userId, fn ($q) => $q->where('user_id', $filterDTO->userId))
            ->when($filterDTO->search, function ($q) use ($filterDTO) {
                $searchTerm = '%' . $filterDTO->search . '%';
                return $q->where(function ($query) use ($searchTerm) {
                    $query->where('detail', 'LIKE', $searchTerm)
                        ->orWhere('amount', 'LIKE', $searchTerm)
                        ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                            $categoryQuery->where('name', 'LIKE', $searchTerm);
                        })
                        ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                            $userQuery->where('name', 'LIKE', $searchTerm);
                        });
                });
            });

        return $query->get();
    }

    public function create(CreateIncomeDTO $dto): Income
    {
        try {
            $income = new Income();
            $income->income_category_id = $dto->incomeCategoryId;
            $income->user_id = $dto->userId;
            $income->date = $dto->date;
            $income->detail = $dto->detail;
            $income->amount = $dto->amount;
            $income->save();

            return $income->load(['category', 'user']);
        } catch (\Exception $e) {
            throw new \Exception('Error al crear el ingreso: ' . $e->getMessage());
        }
    }

    public function update(UpdateIncomeDTO $dto): Income
    {
        $income = Income::find($dto->id);

        if (! $income) {
            throw new \Exception('Ingreso no encontrado');
        }

        try {
            $income->income_category_id = $dto->incomeCategoryId;
            $income->user_id = $dto->userId;
            $income->date = $dto->date;
            $income->detail = $dto->detail;
            $income->amount = $dto->amount;
            $income->save();

            return $income->load(['category', 'user']);
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar el ingreso: ' . $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $income = Income::find($id);

        if (! $income) {
            throw new \Exception('Ingreso no encontrado');
        }

        try {
            $income->delete();
        } catch (\Exception $e) {
            throw new \Exception('Error al eliminar el ingreso: ' . $e->getMessage());
        }
    }
}
