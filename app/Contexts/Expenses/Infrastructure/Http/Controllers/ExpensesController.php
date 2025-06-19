<?php

namespace App\Contexts\Expenses\Infrastructure\Http\Controllers;

use App\Contexts\Expenses\Application\CreateExpenseUseCase;
use App\Contexts\Expenses\Application\DeleteExpenseUseCase;
use App\Contexts\Expenses\Application\DTOs\CreateExpenseDTO;
use App\Contexts\Expenses\Application\DTOs\UpdateExpenseDTO;
use App\Contexts\Expenses\Application\GetExpensesByTransportUseCase;
use App\Contexts\Expenses\Application\UpdateExpenseUseCase;
use App\Contexts\Expenses\Domain\Repositories\ExpensesRepository;
use App\Contexts\Expenses\Infrastructure\Http\Requests\CreateExpenseRequest;
use App\Contexts\Expenses\Infrastructure\Http\Requests\UpdateExpenseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ExpensesController extends Controller
{
    public function __construct(
        private readonly ExpensesRepository $repository
    ) {
    }

    public function index(int $transportId): JsonResponse
    {
        try {
            $useCase = new GetExpensesByTransportUseCase($this->repository);
            $expenses = $useCase($transportId);

            return response()->json($expenses);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al obtener los gastos'], 500);
        }
    }

    public function store(CreateExpenseRequest $request, int $transportId): JsonResponse
    {
        try {
            $dto = new CreateExpenseDTO(
                transportId: $transportId,
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount')
            );

            $useCase = new CreateExpenseUseCase($this->repository);
            $expense = $useCase($dto);

            return response()->json($expense, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el gasto'], 500);
        }
    }

    public function update(UpdateExpenseRequest $request, int $transportId, int $expenseId): JsonResponse
    {
        try {
            $dto = new UpdateExpenseDTO(
                id: $expenseId,
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount')
            );

            $useCase = new UpdateExpenseUseCase($this->repository);
            $expense = $useCase($dto);

            return response()->json($expense);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Gasto no encontrado') {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            return response()->json(['message' => 'Error al actualizar el gasto'], 500);
        }
    }

    public function destroy(int $transportId, int $expenseId): JsonResponse
    {
        try {
            $useCase = new DeleteExpenseUseCase($this->repository);
            $useCase($expenseId);

            return response()->json(['message' => 'Gasto eliminado correctamente']);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Gasto no encontrado') {
                return response()->json(['message' => $e->getMessage()], 404);
            }

            return response()->json(['message' => 'Error al eliminar el gasto'], 500);
        }
    }
}
