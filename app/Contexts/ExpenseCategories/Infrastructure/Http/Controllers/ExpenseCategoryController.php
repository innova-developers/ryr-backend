<?php

namespace App\Contexts\ExpenseCategories\Infrastructure\Http\Controllers;

use App\Contexts\ExpenseCategories\Application\CreateExpenseCategoryUseCase;
use App\Contexts\ExpenseCategories\Application\DeleteExpenseCategoryUseCase;
use App\Contexts\ExpenseCategories\Application\DTO\CreateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Application\DTO\UpdateExpenseCategoryDTO;
use App\Contexts\ExpenseCategories\Application\GetExpenseCategoriesUseCase;
use App\Contexts\ExpenseCategories\Application\UpdateExpenseCategoryUseCase;
use App\Contexts\ExpenseCategories\Infrastructure\Http\Requests\CreateExpenseCategoryRequest;
use App\Contexts\ExpenseCategories\Infrastructure\Http\Requests\UpdateExpenseCategoryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly CreateExpenseCategoryUseCase $createUseCase,
        private readonly UpdateExpenseCategoryUseCase $updateUseCase,
        private readonly DeleteExpenseCategoryUseCase $deleteUseCase,
        private readonly GetExpenseCategoriesUseCase $getUseCase,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $request->has('active') && $request->boolean('active')
            ? $this->getUseCase->executeActive()
            : $this->getUseCase->execute();

        return response()->json($categories);
    }

    public function show(int $id): JsonResponse
    {
        $category = $this->getUseCase->executeById($id);

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada',
            ], 404);
        }

        return response()->json($category);
    }

    public function store(CreateExpenseCategoryRequest $request): JsonResponse
    {
        try {
            $dto = new CreateExpenseCategoryDTO(
                name: $request->validated('name'),
                description: $request->validated('description'),
                isActive: $request->validated('is_active', true),
                isExtraordinary: (bool) $request->validated('is_extraordinary', false),
            );

            $category = $this->createUseCase->execute($dto);

            return response()->json($category, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la categoría: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateExpenseCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $dto = new UpdateExpenseCategoryDTO(
                name: $request->validated('name'),
                description: $request->validated('description'),
                isActive: true,
                isExtraordinary: (bool) $request->validated('is_extraordinary', false),
            );

            $category = $this->updateUseCase->execute($id, $dto);

            return response()->json($category);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la categoría: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->deleteUseCase->execute($id);

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la categoría: ' . $e->getMessage(),
            ], 500);
        }
    }
}
