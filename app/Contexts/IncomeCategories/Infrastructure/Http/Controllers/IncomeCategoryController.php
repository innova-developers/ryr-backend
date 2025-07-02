<?php

namespace App\Contexts\IncomeCategories\Infrastructure\Http\Controllers;

use App\Contexts\IncomeCategories\Application\CreateIncomeCategoryUseCase;
use App\Contexts\IncomeCategories\Application\DeleteIncomeCategoryUseCase;
use App\Contexts\IncomeCategories\Application\DTOs\CreateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Application\DTOs\UpdateIncomeCategoryDTO;
use App\Contexts\IncomeCategories\Application\UpdateIncomeCategoryUseCase;
use App\Contexts\IncomeCategories\Domain\Repositories\IncomeCategoriesRepository;
use App\Contexts\IncomeCategories\Infrastructure\Http\Requests\CreateIncomeCategoryRequest;
use App\Contexts\IncomeCategories\Infrastructure\Http\Requests\UpdateIncomeCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class IncomeCategoryController extends Controller
{
    public function __construct(
        private readonly IncomeCategoriesRepository $repository
    ) {
    }

    public function index(): JsonResponse
    {
        try {
            $categories = $this->repository->findAll()->toArray();

            return response()->json($categories);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las categorías de ingresos: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function store(CreateIncomeCategoryRequest $request): JsonResponse
    {
        try {
            $dto = new CreateIncomeCategoryDTO(
                name: $request->input('name'),
                description: $request->input('description')
            );

            $useCase = new CreateIncomeCategoryUseCase($this->repository);
            $category = $useCase($dto);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Categoría de ingreso creada exitosamente',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la categoría de ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $incomeCategoryId): JsonResponse
    {
        try {
            $category = $this->repository->findById($incomeCategoryId);

            return response()->json($category);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Categoría de ingreso no encontrada') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la categoría de ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateIncomeCategoryRequest $request, int $incomeCategoryId): JsonResponse
    {
        try {
            $dto = new UpdateIncomeCategoryDTO(
                id: $incomeCategoryId,
                name: $request->input('name'),
                description: $request->input('description')
            );

            $useCase = new UpdateIncomeCategoryUseCase($this->repository);
            $category = $useCase($dto);

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Categoría de ingreso actualizada exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Categoría de ingreso no encontrada') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la categoría de ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $incomeCategoryId): JsonResponse
    {
        try {
            $useCase = new DeleteIncomeCategoryUseCase($this->repository);
            $useCase($incomeCategoryId);

            return response()->json([
                'success' => true,
                'message' => 'Categoría de ingreso eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'Categoría de ingreso no encontrada') {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la categoría de ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }
}
