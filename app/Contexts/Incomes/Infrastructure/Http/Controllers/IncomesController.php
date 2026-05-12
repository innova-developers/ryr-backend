<?php

namespace App\Contexts\Incomes\Infrastructure\Http\Controllers;

use App\Contexts\Incomes\Application\CreateIncomeUseCase;
use App\Contexts\Incomes\Application\DeleteIncomeUseCase;
use App\Contexts\Incomes\Application\DTOs\CreateIncomeDTO;
use App\Contexts\Incomes\Application\DTOs\IncomeFilterDTO;
use App\Contexts\Incomes\Application\DTOs\UpdateIncomeDTO;
use App\Contexts\Incomes\Application\UpdateIncomeUseCase;
use App\Contexts\Incomes\Domain\Repositories\IncomesRepository;
use App\Contexts\Incomes\Infrastructure\Http\Requests\CreateIncomeRequest;
use App\Contexts\Incomes\Infrastructure\Http\Requests\UpdateIncomeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IncomesController extends Controller
{
    public function __construct(
        private readonly IncomesRepository $repository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filterDTO = new IncomeFilterDTO(
                $request->get('dateFrom'),
                $request->get('dateTo'),
                $request->get('income_category_id'),
                $request->get('user_id'),
                $request->get('search'),
                $request->get('page', 1),
                $request->get('per_page', 15)
            );

            $incomes = $this->repository->findAll($filterDTO);

            return response()->json([
                'data' => $incomes->items(),
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
                'per_page' => $incomes->perPage(),
                'total' => $incomes->total(),
                'from' => $incomes->firstItem(),
                'to' => $incomes->lastItem(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ingresos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateIncomeRequest $request): JsonResponse
    {
        try {
            $dto = new CreateIncomeDTO(
                incomeCategoryId: $request->input('income_category_id'),
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount'),
                userId: $request->input('user_id')
            );

            $useCase = new CreateIncomeUseCase($this->repository);
            $income = $useCase($dto);

            // Obtener el modelo completo con relaciones
            $incomeModel = $this->repository->findById($income['id']);

            return response()->json($incomeModel, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateIncomeRequest $request, int $incomeId): JsonResponse
    {
        try {
            $dto = new UpdateIncomeDTO(
                id: $incomeId,
                incomeCategoryId: $request->input('income_category_id'),
                date: new \DateTime($request->input('date')),
                detail: $request->input('detail'),
                amount: $request->input('amount'),
                userId: $request->input('user_id')
            );

            $useCase = new UpdateIncomeUseCase($this->repository);
            $income = $useCase($dto);

            // Obtener el modelo completo con relaciones
            $incomeModel = $this->repository->findById($income['id']);

            return response()->json($incomeModel, 201);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Ingreso no encontrado') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $incomeId): JsonResponse
    {
        try {
            $useCase = new DeleteIncomeUseCase($this->repository);
            $useCase($incomeId);

            return response()->json([
                'success' => true,
                'message' => 'Ingreso eliminado exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Ingreso no encontrado') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function indexByUser(int $userId): JsonResponse
    {
        try {
            $filterDTO = new IncomeFilterDTO(
                null,
                null,
                null,
                $userId,
                null,
                1,
                9999
            );

            $incomes = $this->repository->findAll($filterDTO);

            return response()->json([
                'data' => $incomes->items(),
                'current_page' => $incomes->currentPage(),
                'last_page' => $incomes->lastPage(),
                'per_page' => $incomes->perPage(),
                'total' => $incomes->total(),
                'from' => $incomes->firstItem(),
                'to' => $incomes->lastItem(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ingresos del usuario: ' . $e->getMessage(),
            ], 500);
        }
    }
}
