<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BalanceController extends Controller
{
    /**
     * Balance general por FECHAS REALES:
     * - Ingresos: cobros (current_accounts type=credit, status=OK) por transaction_date (fecha real de cobro).
     *   Coincide con el pool de cobranza.
     * - Egresos: gastos por su fecha real (expenses.date). Los balances finales usan solo egresos ordinarios
     *   (se excluyen las categorías "extraordinarias", igual que la vista).
     */
    public function general(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $branchId = $request->get('branch_id');

        // INGRESOS = cobros confirmados por fecha real de cobro (transaction_date)
        $ingresosQuery = CurrentAccount::query()
            ->where('type', 'credit')
            ->where('status', CurrentAccountStatus::OK->value);

        if ($dateFrom) {
            $ingresosQuery->whereDate('transaction_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ingresosQuery->whereDate('transaction_date', '<=', $dateTo);
        }
        if ($branchId) {
            $ingresosQuery->whereHas('customer', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }
        $totalIngresos = (float) $ingresosQuery->sum('amount');

        // EGRESOS por fecha real (expenses.date). Separar ordinarios vs extraordinarios por nombre de categoría.
        $baseEgresos = Expense::query()
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');
        if ($dateFrom) {
            $baseEgresos->whereDate('expenses.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseEgresos->whereDate('expenses.date', '<=', $dateTo);
        }

        $totalEgresosOrdinarios = (float) (clone $baseEgresos)
            ->where(function ($q) {
                $q->whereNull('expense_categories.name')
                  ->orWhere('expense_categories.name', 'NOT LIKE', '%extra%');
            })
            ->sum('expenses.amount');

        $totalEgresosExtraordinarios = (float) (clone $baseEgresos)
            ->where('expense_categories.name', 'LIKE', '%extra%')
            ->sum('expenses.amount');

        $balanceFinal = $totalIngresos - $totalEgresosOrdinarios;

        return response()->json([
            'success' => true,
            'data' => [
                'total_ingresos' => $totalIngresos,
                'total_egresos_ordinarios' => $totalEgresosOrdinarios,
                'total_egresos_extraordinarios' => $totalEgresosExtraordinarios,
                'balance_final' => $balanceFinal,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
