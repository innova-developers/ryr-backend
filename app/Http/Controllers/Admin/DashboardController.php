<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Models\Commission;
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

        $applyCommissionFilters = function ($q) use ($dateFrom, $dateTo, $branchId) {
            if ($dateFrom) {
                $q->where('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->where('date', '<=', $dateTo);
            }
            if ($branchId) {
                $q->where('branch_id', $branchId);
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

        return response()->json([
            'total_commissions' => $totalCommissions,
            'total_customers' => $totalCustomers,
            'total_transports' => $totalTransports,
            'total_expenses' => $totalExpenses,
        ]);
    }
}
