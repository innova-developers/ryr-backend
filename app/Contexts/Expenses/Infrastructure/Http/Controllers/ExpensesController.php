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
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpensesController extends Controller
{
    public function __construct(
        private readonly ExpensesRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            // Si se especifica transport_id, usar el caso de uso específico
            if ($request->has('transport_id')) {
                $useCase = new GetExpensesByTransportUseCase($this->repository);
                $expenses = $useCase($request->integer('transport_id'));
            } else {
                // Construir el DTO de filtros
                $filterDTO = new \App\Contexts\Expenses\Application\DTOs\ExpenseFilterDTO(
                    $request->get('dateFrom'),
                    $request->get('dateTo'),
                    $request->get('category'),
                    $request->get('transport')
                );
                $expenses = $this->repository->findAll($filterDTO)->toArray();
            }

            return response()->json($expenses);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los gastos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateExpenseRequest $request): JsonResponse
    {
        try {
            $dto = new CreateExpenseDTO(
                transportId: $request->input('transport_id'),
                expenseCategoryId: $request->input('expense_category_id'),
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount')
            );

            $useCase = new CreateExpenseUseCase($this->repository);
            $expense = $useCase($dto);

            // Obtener el modelo completo con relaciones
            $expenseModel = $this->repository->findById($expense['id']);

            return response()->json([
                'success' => true,
                'data' => $expenseModel,
                'message' => 'Gasto creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el gasto: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateExpenseRequest $request, int $expenseId): JsonResponse
    {
        try {
            $dto = new UpdateExpenseDTO(
                id: $expenseId,
                transportId: $request->input('transport_id'),
                expenseCategoryId: $request->input('expense_category_id'),
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount')
            );

            $useCase = new UpdateExpenseUseCase($this->repository);
            $expense = $useCase($dto);

            // Obtener el modelo completo con relaciones
            $expenseModel = $this->repository->findById($expense['id']);

            return response()->json([
                'success' => true,
                'data' => $expenseModel,
                'message' => 'Gasto actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Gasto no encontrado') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el gasto: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $expenseId): JsonResponse
    {
        try {
            $useCase = new DeleteExpenseUseCase($this->repository);
            $useCase($expenseId);

            return response()->json([
                'success' => true,
                'message' => 'Gasto eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Gasto no encontrado') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el gasto: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Método para mantener compatibilidad con la ruta anterior
    public function indexByTransport(int $transportId): JsonResponse
    {
        try {
            $useCase = new GetExpensesByTransportUseCase($this->repository);
            $expenses = $useCase($transportId);

            return response()->json($expenses);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los gastos: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Método para mantener compatibilidad con la ruta anterior
    public function storeForTransport(CreateExpenseRequest $request, int $transportId): JsonResponse
    {
        try {
            $dto = new CreateExpenseDTO(
                transportId: $transportId,
                expenseCategoryId: $request->input('expense_category_id'),
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount')
            );

            $useCase = new CreateExpenseUseCase($this->repository);
            $expense = $useCase($dto);

            // Obtener el modelo completo con relaciones
            $expenseModel = $this->repository->findById($expense['id']);

            return response()->json([
                'success' => true,
                'data' => $expenseModel,
                'message' => 'Gasto creado exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el gasto: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Método para mantener compatibilidad con la ruta anterior
    public function updateForTransport(UpdateExpenseRequest $request, int $transportId, int $expenseId): JsonResponse
    {
        try {
            $dto = new UpdateExpenseDTO(
                id: $expenseId,
                transportId: $transportId,
                expenseCategoryId: $request->input('expense_category_id'),
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount')
            );

            $useCase = new UpdateExpenseUseCase($this->repository);
            $expense = $useCase($dto);

            // Obtener el modelo completo con relaciones
            $expenseModel = $this->repository->findById($expense['id']);

            return response()->json([
                'success' => true,
                'data' => $expenseModel,
                'message' => 'Gasto actualizado exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Gasto no encontrado') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el gasto: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Método para mantener compatibilidad con la ruta anterior
    public function destroyForTransport(int $transportId, int $expenseId): JsonResponse
    {
        try {
            $useCase = new DeleteExpenseUseCase($this->repository);
            $useCase($expenseId);

            return response()->json([
                'success' => true,
                'message' => 'Gasto eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Gasto no encontrado') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el gasto: ' . $e->getMessage(),
            ], 500);
        }
    }
}
