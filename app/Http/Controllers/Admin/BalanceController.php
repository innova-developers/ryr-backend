<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Expense;
use App\Shared\Models\Income;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;

class BalanceController extends Controller
{
    /**
     * Concepto de ingreso manual que NO corresponde a cobranzas de comisiones.
     *
     * El resto de las categorías de Ingresos ("efectivo", "transferencia", "cheques",
     * "echeq", "otros") son formas de pago con las que se registra a mano la misma
     * plata que ya entra por cuenta corriente, así que sumarlas duplicaría los
     * ingresos. "Ventas" es el único concepto independiente, y por eso es el único
     * que impacta en el balance final.
     */
    private const CONCEPTO_INGRESO_INDEPENDIENTE = 'ventas';

    /**
     * Balance general por FECHAS REALES:
     * - Ingresos por cobranza: current_accounts type=credit, status=OK, por
     *   transaction_date (fecha real de cobro). Coincide con el pool de cobranza.
     * - Ingresos independientes: movimientos manuales de Ingresos con concepto
     *   "Ventas", por su fecha real. Se informan aparte y suman al balance.
     * - Otros ingresos manuales: se informan como referencia pero NO suman, porque
     *   duplican cobranzas ya contadas.
     * - Egresos: gastos por su fecha real. El balance final usa sólo los ordinarios.
     */
    public function general(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $branchId = $request->get('branch_id');

        // ---- INGRESOS POR COBRANZA (fecha real de cobro) ----
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

        // ---- INGRESOS MANUALES (tabla incomes) ----
        // La sucursal se resuelve por el empleado que cargó el movimiento, porque
        // incomes no tiene branch_id propio.
        $ingresosManuales = Income::query()
            ->leftJoin('income_categories', 'income_categories.id', '=', 'incomes.income_category_id');

        if ($dateFrom) {
            $ingresosManuales->whereDate('incomes.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $ingresosManuales->whereDate('incomes.date', '<=', $dateTo);
        }
        if ($branchId) {
            $this->scopeByUserBranch($ingresosManuales, 'incomes', $branchId);
        }

        $totalIngresosVentas = (float) (clone $ingresosManuales)
            ->where('income_categories.name', 'LIKE', '%' . self::CONCEPTO_INGRESO_INDEPENDIENTE . '%')
            ->sum('incomes.amount');

        $totalIngresosManualesOtros = (float) (clone $ingresosManuales)
            ->where(function ($q) {
                $q->whereNull('income_categories.name')
                  ->orWhere('income_categories.name', 'NOT LIKE', '%' . self::CONCEPTO_INGRESO_INDEPENDIENTE . '%');
            })
            ->sum('incomes.amount');

        // ---- EGRESOS (fecha real) ----
        $baseEgresos = Expense::query()
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id');

        if ($dateFrom) {
            $baseEgresos->whereDate('expenses.date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseEgresos->whereDate('expenses.date', '<=', $dateTo);
        }
        // Antes los egresos no se acotaban por sucursal (la condición dependía de una
        // columna expenses.branch_id que no existe), así que al elegir una sucursal el
        // balance mezclaba ingresos de una con gastos de todas.
        if ($branchId) {
            $this->scopeByUserBranch($baseEgresos, 'expenses', $branchId);
        }

        // La clasificación sale del flag de la categoría, no de su nombre.
        $hasFlag = Schema::hasColumn('expense_categories', 'is_extraordinary');

        $totalEgresosOrdinarios = (float) (clone $baseEgresos)
            ->where(function ($q) use ($hasFlag) {
                if ($hasFlag) {
                    $q->whereNull('expense_categories.is_extraordinary')
                      ->orWhere('expense_categories.is_extraordinary', false);
                } else {
                    $q->whereNull('expense_categories.name')
                      ->orWhere('expense_categories.name', 'NOT LIKE', '%extra%');
                }
            })
            ->sum('expenses.amount');

        $totalEgresosExtraordinarios = (float) (clone $baseEgresos)
            ->where(function ($q) use ($hasFlag) {
                if ($hasFlag) {
                    $q->where('expense_categories.is_extraordinary', true);
                } else {
                    $q->where('expense_categories.name', 'LIKE', '%extra%');
                }
            })
            ->sum('expenses.amount');

        $balanceFinal = $totalIngresos + $totalIngresosVentas - $totalEgresosOrdinarios;

        return response()->json([
            'success' => true,
            'data' => [
                'total_ingresos' => $totalIngresos,
                'total_ingresos_ventas' => $totalIngresosVentas,
                'total_ingresos_manuales_otros' => $totalIngresosManualesOtros,
                'total_egresos_ordinarios' => $totalEgresosOrdinarios,
                'total_egresos_extraordinarios' => $totalEgresosExtraordinarios,
                'balance_final' => $balanceFinal,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Acota una consulta a la sucursal del empleado que cargó el movimiento.
     *
     * Ni expenses ni incomes tienen branch_id propio; lo que sí quedó cargado (desde
     * sprint 8) es el user_id del empleado, y de ahí se deriva la sucursal.
     */
    private function scopeByUserBranch($query, string $table, $branchId): void
    {
        if (! Schema::hasColumn($table, 'user_id')) {
            return;
        }

        $query->whereExists(function ($q) use ($table, $branchId) {
            $q->selectRaw('1')
              ->from('users')
              ->whereColumn('users.id', "{$table}.user_id")
              ->where('users.branch_id', $branchId);
        });
    }
}
