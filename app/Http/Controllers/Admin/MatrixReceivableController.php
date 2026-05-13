<?php

namespace App\Http\Controllers\Admin;

use App\Services\MatrixCommissionService;
use App\Shared\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MatrixReceivableController extends Controller
{
    private MatrixCommissionService $service;

    public function __construct()
    {
        $this->service = new MatrixCommissionService();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $franchiseId = null;

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            $franchiseId = $user->franchise_id;
        } elseif ($request->has('franchise_id')) {
            $franchiseId = (int) $request->input('franchise_id');
        }

        $receivables = $this->service->getReceivablesByFranchise(
            franchiseId: $franchiseId,
            status: $request->input('status'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
            page: (int) $request->input('page', 1),
            perPage: (int) $request->input('per_page', 15),
        );

        return response()->json($receivables);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $franchiseId = null;

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            $franchiseId = $user->franchise_id;
        } elseif ($request->has('franchise_id')) {
            $franchiseId = (int) $request->input('franchise_id');
        }

        return response()->json($this->service->getSummary($franchiseId));
    }

    public function bulkMarkAsPaid(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->role->isMatrixAdmin()) {
            return response()->json(['message' => 'Solo administradores de matriz pueden marcar como pagado'], 403);
        }

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'payment_reference' => 'nullable|string|max:255',
        ]);

        $count = $this->service->bulkMarkAsPaid(
            $request->input('ids'),
            $request->input('payment_reference')
        );

        return response()->json(['updated' => $count]);
    }

    public function markAsPaid(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->role->isMatrixAdmin()) {
            return response()->json(['message' => 'Solo administradores de matriz pueden marcar como pagado'], 403);
        }

        $receivable = $this->service->markAsPaid($id, $request->input('payment_reference'));

        return response()->json($receivable);
    }

    public function markAsCancelled(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->role->isMatrixAdmin()) {
            return response()->json(['message' => 'Solo administradores de matriz pueden cancelar'], 403);
        }

        $receivable = $this->service->markAsCancelled($id, $request->input('notes'));

        return response()->json($receivable);
    }
}
