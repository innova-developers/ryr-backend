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

        $commissionsQuery = Commission::query();
        if ($dateFrom) {
            $commissionsQuery->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $commissionsQuery->where('date', '<=', $dateTo);
        }

        $totalCommissions = (clone $commissionsQuery)->count();

        $customerIdsQuery = Commission::query()->select('client_id');
        if ($dateFrom) {
            $customerIdsQuery->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $customerIdsQuery->where('date', '<=', $dateTo);
        }
        $totalCustomers = $customerIdsQuery->distinct()->count('client_id');

        $totalTransports = Transport::whereHas('commissions', function ($q) use ($dateFrom, $dateTo) {
            if ($dateFrom) {
                $q->where('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $q->where('date', '<=', $dateTo);
            }
        })->count();

        $expensesQuery = Expense::query();
        if ($dateFrom) {
            $expensesQuery->where('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expensesQuery->where('date', '<=', $dateTo);
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
