<?php

namespace App\Contexts\Expenses\Infrastructure\Repositories;

use App\Contexts\Expenses\Application\DTOs\CreateExpenseDTO;
use App\Contexts\Expenses\Application\DTOs\UpdateExpenseDTO;
use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;
use App\Shared\Models\Expense;

class ExpensesEloquentRepository implements ExpensesRepository
{
    public function findByTransportId(int $transportId): array
    {
        return Expense::where('transport_id', $transportId)
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }

    public function findById(int $id): Expense
    {
        $expense = Expense::find($id);

        if (! $expense) {
            throw new \Exception('Gasto no encontrado');
        }

        return $expense;
    }

    public function create(CreateExpenseDTO $dto): Expense
    {
        try {
            $expense = new Expense();
            $expense->transport_id = $dto->transportId;
            $expense->date = $dto->date;
            $expense->detail = $dto->detail;
            $expense->amount = $dto->amount;
            $expense->save();

            return $expense;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear el gasto: ' . $e->getMessage());
        }
    }

    public function update(UpdateExpenseDTO $dto): Expense
    {
        $expense = Expense::find($dto->id);

        if (! $expense) {
            throw new \Exception('Gasto no encontrado');
        }

        try {
            $expense->date = $dto->date;
            $expense->detail = $dto->detail;
            $expense->amount = $dto->amount;
            $expense->save();

            return $expense;
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar el gasto: ' . $e->getMessage());
        }
    }

    public function delete(int $id): void
    {
        $expense = Expense::find($id);

        if (! $expense) {
            throw new \Exception('Gasto no encontrado');
        }

        try {
            $expense->delete();
        } catch (\Exception $e) {
            throw new \Exception('Error al eliminar el gasto: ' . $e->getMessage());
        }
    }
}
