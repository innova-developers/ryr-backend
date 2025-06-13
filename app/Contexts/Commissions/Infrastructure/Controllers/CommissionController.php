<?php

namespace App\Http\Controllers\Commissions\Infrastructure\Controllers;

use App\Http\Controllers\Controller;
use App\Contexts\Commissions\Application\DeleteCommissionUseCase;
use Illuminate\Http\JsonResponse;

class CommissionController extends Controller
{
    private $deleteCommissionUseCase;

    public function __construct(DeleteCommissionUseCase $deleteCommissionUseCase)
    {
        $this->deleteCommissionUseCase = $deleteCommissionUseCase;
    }

    public function destroy(int $id): JsonResponse
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