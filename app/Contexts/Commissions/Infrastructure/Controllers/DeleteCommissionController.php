<?php

namespace App\Contexts\Commissions\Infrastructure\Controllers;

use App\Contexts\Commissions\Application\DeleteCommissionUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DeleteCommissionController extends Controller
{
    public function __construct(
        private readonly DeleteCommissionUseCase $deleteCommissionUseCase
    ) {
    }

    public function __invoke(int $id): JsonResponse
    {
        try {
            $this->deleteCommissionUseCase->__invoke($id);
            return response()->json(['message' => 'Comisión eliminada correctamente']);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Comisión no encontrada')) {
                return response()->json(['error' => $e->getMessage()], 404);
            }
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
} 