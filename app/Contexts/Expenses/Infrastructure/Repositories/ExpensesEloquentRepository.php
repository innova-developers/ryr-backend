<?php

namespace App\Contexts\Expenses\Infrastructure\Repositories;

use App\Contexts\Expenses\Application\DTOs\CreateExpenseDTO;
use App\Contexts\Expenses\Application\DTOs\ExpenseFilterDTO;
use App\Contexts\Expenses\Application\DTOs\UpdateExpenseDTO;
use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ExpensesEloquentRepository implements ExpensesRepository
{
    public function findByTransportId(int $transportId): array
    {
        $query = Expense::where('transport_id', $transportId)
            ->with(['transport', 'category', 'user'])
            ->orderBy('date', 'desc');

        // Filtrar por sucursal según el rol del usuario
        $user = Auth::user();
        if ($user && $user->branch_id) {
            // Cadetes, mostradores y administradores con sucursal solo ven gastos de usuarios de su sucursal
            if (in_array($user->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO, UserRole::MOSTRADOR, UserRole::ADMINISTRADOR])) {
                $query->whereHas('user', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            }
        }

        return $query->get()->toArray();
    }

    public function findById(int $id): Expense
    {
        $expense = Expense::with(['transport', 'category', 'user'])->find($id);

        if (! $expense) {
            throw new \Exception('Gasto no encontrado');
        }

        return $expense;
    }

    public function findAll(ExpenseFilterDTO $filterDTO): Collection
    {
        $query = Expense::with(['transport', 'category', 'user'])
            ->orderBy('date', 'desc')
            ->when($filterDTO->dateFrom, fn ($q) => $q->where('date', '>=', $filterDTO->dateFrom))
            ->when($filterDTO->dateTo, fn ($q) => $q->where('date', '<=', $filterDTO->dateTo))
            ->when($filterDTO->categoryId, fn ($q) => $q->where('expense_category_id', $filterDTO->categoryId))
            ->when($filterDTO->transportId, fn ($q) => $q->where('transport_id', $filterDTO->transportId))
            ->when($filterDTO->userId, fn ($q) => $q->where('user_id', $filterDTO->userId));

        // Filtrar por sucursal según el rol del usuario
        $user = Auth::user();
        if ($user && $user->branch_id) {
            // Cadetes, mostradores y administradores con sucursal solo ven gastos de usuarios de su sucursal
            if (in_array($user->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO, UserRole::MOSTRADOR, UserRole::ADMINISTRADOR])) {
                $query->whereHas('user', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            }
        }

        return $query->get();
    }

    public function create(CreateExpenseDTO $dto): Expense
    {
        try {
            $expense = new Expense();
            $expense->transport_id = $dto->transportId;
            $expense->expense_category_id = $dto->expenseCategoryId;
            $expense->user_id = $dto->userId;
            $expense->date = $dto->date;
            $expense->detail = $dto->detail;
            $expense->amount = $dto->amount;
            $expense->save();

            return $expense->load(['transport', 'category', 'user']);
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
            $expense->transport_id = $dto->transportId;
            $expense->expense_category_id = $dto->expenseCategoryId;
            $expense->user_id = $dto->userId;
            $expense->date = $dto->date;
            $expense->detail = $dto->detail;
            $expense->amount = $dto->amount;
            $expense->save();

            return $expense->load(['transport', 'category', 'user']);
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
