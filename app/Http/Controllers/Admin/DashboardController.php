<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Expense;
use App\Shared\Models\Transport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $branchId = $request->get('branch_id');
        $user = $request->user();

        // Estados seleccionados desde el filtro del Inicio (multi-select). Puede llegar como
        // statuses[]=A&statuses[]=B (array) o como "A,B" (string). Se normaliza a array de values.
        $statuses = $request->get('statuses');
        if (is_string($statuses)) {
            $statuses = array_filter(array_map('trim', explode(',', $statuses)), fn ($s) => $s !== '');
        }
        $statuses = is_array($statuses) ? array_values(array_filter($statuses, fn ($s) => $s !== null && $s !== '')) : [];

        // Estados de pago que el listado oculta por defecto (ver CommissionsEloquentRepository::list()).
        // El dashboard debe reflejar las MISMAS operaciones que ve el usuario en el listado, por eso
        // se acota igual: scope por sucursal del usuario + exclusión de estados de pago pendiente.
        $defaultExcludedStatuses = [
            CommissionStatus::PENDIENTE_PAGO->value,
            CommissionStatus::PAGO_VALIDACION->value,
            CommissionStatus::PAGO_CONFIRMADO->value,
        ];

        $applyCommissionFilters = function ($q) use ($dateFrom, $dateTo, $branchId, $user, $defaultExcludedStatuses, $statuses) {
            if ($dateFrom) {
                $q->whereDate('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->whereDate('date', '<=', $dateTo);
            }
            // Si el usuario está acotado a una sucursal, respetarla (igual que el listado);
            // si no, aplicar el branch_id explícito recibido por parámetro.
            if ($user && $user->branch_id) {
                $q->where('branch_id', $user->branch_id);
            } elseif ($branchId) {
                $q->where('branch_id', $branchId);
            }
            // Filtro de estado del Inicio: si el usuario eligió estados, mostrar SOLO esos
            // (por defecto el front manda PAGO_CONFIRMADO, unificando el conteo con el Balance).
            // Si no eligió ninguno, se mantiene el comportamiento anterior (excluir pendientes de pago).
            if (! empty($statuses)) {
                $q->whereIn('status', $statuses);
            } else {
                $q->whereNotIn('status', $defaultExcludedStatuses);
            }
        };

        $commissionsQuery = Commission::query();
        $applyCommissionFilters($commissionsQuery);
        $totalCommissions = (clone $commissionsQuery)->count();

        $customerIdsQuery = Commission::query()->select('client_id');
        $applyCommissionFilters($customerIdsQuery);
        $totalCustomers = $customerIdsQuery->distinct()->count('client_id');

        $totalTransports = Transport::whereHas('commissions', function ($q) use ($applyCommissionFilters) {
            $applyCommissionFilters($q);
        })->count();

        $expensesQuery = Expense::query();
        if ($dateFrom) {
            $expensesQuery->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expensesQuery->where('date', '<=', $dateTo);
        }
        if ($branchId && \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'branch_id')) {
            $expensesQuery->where('branch_id', $branchId);
        }
        $totalExpenses = $expensesQuery->sum('amount');

        // ==== AGREGADOS PARA LOS GRÁFICOS (sobre TODO el set filtrado, no la lista paginada) ====

        // Comisiones por mes (índice 0=Enero ... 11=Diciembre) dentro del rango filtrado
        $monthlyQuery = Commission::query();
        $applyCommissionFilters($monthlyQuery);
        $monthlyRows = $monthlyQuery
            ->selectRaw('MONTH(date) as m, COUNT(*) as c')
            ->groupByRaw('MONTH(date)')
            ->pluck('c', 'm');
        $commissionsByMonth = [];
        for ($i = 1; $i <= 12; $i++) {
            $commissionsByMonth[] = (int) ($monthlyRows[$i] ?? 0);
        }

        // Estado de las comisiones
        $statusQuery = Commission::query();
        $applyCommissionFilters($statusQuery);
        $commissionStatuses = $statusQuery
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count])
            ->values();

        // Comisiones diarias (últimos 7 días)
        $dailyQuery = Commission::query();
        if ($user && $user->branch_id) {
            $dailyQuery->where('branch_id', $user->branch_id);
        } elseif ($branchId) {
            $dailyQuery->where('branch_id', $branchId);
        }
        if (! empty($statuses)) {
            $dailyQuery->whereIn('status', $statuses);
        } else {
            $dailyQuery->whereNotIn('status', $defaultExcludedStatuses);
        }
        $dailyQuery->whereDate('date', '>=', now()->subDays(6)->toDateString());
        $dailyRows = $dailyQuery
            ->selectRaw('DATE(date) as d, COUNT(*) as c')
            ->groupByRaw('DATE(date)')
            ->pluck('c', 'd');
        $dailyDeliveries = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $dailyDeliveries[] = ['date' => $d, 'count' => (int) ($dailyRows[$d] ?? 0)];
        }

        // Distribución de clientes (nuevos vs recurrentes) entre los clientes del set filtrado
        $clientIdsQuery = Commission::query()->select('client_id');
        $applyCommissionFilters($clientIdsQuery);
        $clientIds = $clientIdsQuery->distinct()->pluck('client_id')->filter()->values();
        $newCustomers = $clientIds->isEmpty() ? 0 : Customer::whereIn('id', $clientIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        $recurringCustomers = max(0, $clientIds->count() - $newCustomers);

        return response()->json([
            'total_commissions' => $totalCommissions,
            'total_customers' => $totalCustomers,
            'total_transports' => $totalTransports,
            'total_expenses' => $totalExpenses,
            'commissions_by_month' => $commissionsByMonth,
            'commission_statuses' => $commissionStatuses,
            'daily_deliveries' => $dailyDeliveries,
            'customers_distribution' => [
                'new' => $newCustomers,
                'recurring' => $recurringCustomers,
            ],
        ]);
    }
}
