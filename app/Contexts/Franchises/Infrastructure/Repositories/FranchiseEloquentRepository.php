<?php

namespace App\Contexts\Franchises\Infrastructure\Repositories;

use App\Contexts\Franchises\Application\DTO\CreateFranchiseDTO;
use App\Contexts\Franchises\Application\DTO\GetFranchisesFiltersDTO;
use App\Contexts\Franchises\Application\DTO\UpdateFranchiseDTO;
use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Expense;
use App\Shared\Models\Franchise;
use App\Shared\Models\Income;
use Illuminate\Database\QueryException;

class FranchiseEloquentRepository implements FranchiseRepository
{
    public function get(?GetFranchisesFiltersDTO $filters = null): array
    {
        $query = Franchise::query();

        if ($filters && $filters->search) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters->search . '%')
                  ->orWhere('address', 'like', '%' . $filters->search . '%')
                  ->orWhere('email', 'like', '%' . $filters->search . '%')
                  ->orWhere('phone', 'like', '%' . $filters->search . '%');
            });
        }

        if ($filters && $filters->status) {
            $query->where('status', $filters->status);
        }

        if ($filters && $filters->sortBy) {
            $allowedSortFields = ['name', 'status', 'commission_percentage_to_matrix', 'created_at', 'contract_start_date'];
            if (in_array($filters->sortBy, $allowedSortFields)) {
                $query->orderBy($filters->sortBy, $filters->sortDirection);
            }
        } else {
            $query->orderBy('name', 'asc');
        }

        if ($filters) {
            $perPage = min($filters->perPage, 100);
            $franchises = $query->paginate($perPage, ['*'], 'page', $filters->page);

            return [
                'data' => $franchises->items(),
                'pagination' => [
                    'current_page' => $franchises->currentPage(),
                    'per_page' => $franchises->perPage(),
                    'total' => $franchises->total(),
                    'last_page' => $franchises->lastPage(),
                    'from' => $franchises->firstItem(),
                    'to' => $franchises->lastItem(),
                ],
            ];
        }

        return $query->get()->toArray();
    }

    public function findById(int $id): ?Franchise
    {
        try {
            return Franchise::with(['owner', 'branches'])->find($id);
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al buscar la franquicia: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findBySlug(string $slug): ?Franchise
    {
        return Franchise::where('slug', $slug)->first();
    }

    public function create(CreateFranchiseDTO $dto): Franchise
    {
        try {
            $franchise = new Franchise();
            $franchise->name = $dto->name;
            $franchise->slug = $dto->slug;
            $franchise->address = $dto->address;
            $franchise->phone = $dto->phone;
            $franchise->email = $dto->email;
            $franchise->commission_percentage_to_matrix = $dto->commission_percentage_to_matrix;
            $franchise->owner_user_id = $dto->owner_user_id;
            $franchise->status = $dto->status;
            $franchise->contract_start_date = $dto->contract_start_date;
            $franchise->contract_end_date = $dto->contract_end_date;
            $franchise->settings = $dto->settings;
            $franchise->save();

            return $franchise;
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al crear la franquicia: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(UpdateFranchiseDTO $dto): Franchise
    {
        $franchise = Franchise::find($dto->id);
        if (! $franchise) {
            throw new \RuntimeException('Franquicia no encontrada');
        }

        try {
            $franchise->name = $dto->name;
            if ($dto->slug) {
                $franchise->slug = $dto->slug;
            }
            $franchise->address = $dto->address;
            $franchise->phone = $dto->phone;
            $franchise->email = $dto->email;
            $franchise->commission_percentage_to_matrix = $dto->commission_percentage_to_matrix;
            $franchise->owner_user_id = $dto->owner_user_id;
            $franchise->status = $dto->status;
            $franchise->contract_start_date = $dto->contract_start_date;
            $franchise->contract_end_date = $dto->contract_end_date;
            $franchise->settings = $dto->settings;
            $franchise->save();

            return $franchise;
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al actualizar la franquicia: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(int $id): bool
    {
        $franchise = Franchise::find($id);
        if (! $franchise) {
            throw new \RuntimeException('Franquicia no encontrada');
        }

        try {
            return $franchise->delete();
        } catch (QueryException $e) {
            throw new \RuntimeException('Error al eliminar la franquicia: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getDashboardStats(int $franchiseId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $franchise = Franchise::with(['branches', 'customers'])->find($franchiseId);

        if (! $dateFrom) {
            $dateFrom = now()->startOfYear()->toDateString();
        }
        if (! $dateTo) {
            $dateTo = now()->toDateString();
        }

        $commissionQuery = Commission::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo]);
        $expenseQuery = Expense::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo]);
        $incomeQuery = Income::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo]);

        $totalCommissions = (clone $commissionQuery)->count();
        $paidCommissionQuery = (clone $commissionQuery)
            ->whereIn('status', [CommissionStatus::PAGO_VALIDACION, CommissionStatus::PAGO_CONFIRMADO]);
        $totalCommissionAmount = (clone $paidCommissionQuery)->sum('total');
        $totalExpenses = (clone $expenseQuery)->sum('amount');
        $totalIncomes = (clone $incomeQuery)->sum('amount');
        $matrixDebt = $totalCommissionAmount * ($franchise->commission_percentage_to_matrix / 100);

        $statusBreakdown = (clone $commissionQuery)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status->value ?? $row->status,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->toArray();

        $dateFormat = config('database.default') === 'sqlite'
            ? "strftime('%Y-%m', date)"
            : "DATE_FORMAT(date, '%Y-%m')";

        $monthlyEvolution = Commission::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', [CommissionStatus::PAGO_VALIDACION, CommissionStatus::PAGO_CONFIRMADO])
            ->selectRaw("{$dateFormat} as month, COUNT(*) as count, COALESCE(SUM(total), 0) as income")
            ->groupByRaw("{$dateFormat}")
            ->orderBy('month')
            ->get()
            ->toArray();

        $monthlyExpenses = Expense::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw("{$dateFormat} as month, COALESCE(SUM(amount), 0) as total")
            ->groupByRaw("{$dateFormat}")
            ->orderBy('month')
            ->get()
            ->keyBy('month')
            ->toArray();

        $evolution = array_map(function ($row) use ($monthlyExpenses, $franchise) {
            $expenses = $monthlyExpenses[$row['month']]['total'] ?? 0;
            $matrixCut = $row['income'] * ($franchise->commission_percentage_to_matrix / 100);

            return [
                'month' => $row['month'],
                'commissions' => (int) $row['count'],
                'income' => round((float) $row['income'], 2),
                'expenses' => round((float) $expenses, 2),
                'matrix' => round($matrixCut, 2),
                'net' => round($row['income'] - $expenses - $matrixCut, 2),
            ];
        }, $monthlyEvolution);

        $topCollectors = Commission::where('commissions.franchise_id', $franchiseId)
            ->whereBetween('commissions.date', [$dateFrom, $dateTo])
            ->whereIn('commissions.status', [CommissionStatus::PAGO_VALIDACION, CommissionStatus::PAGO_CONFIRMADO])
            ->whereNotNull('commissions.cadete_id')
            ->join('users', 'users.id', '=', 'commissions.cadete_id')
            ->selectRaw('users.id, users.name, COUNT(*) as total_commissions, COALESCE(SUM(commissions.total), 0) as total_collected')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_collected')
            ->limit(10)
            ->get()
            ->toArray();

        $expensesByCategory = Expense::where('expenses.franchise_id', $franchiseId)
            ->whereBetween('expenses.date', [$dateFrom, $dateTo])
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->selectRaw('expense_categories.name as category, COALESCE(SUM(expenses.amount), 0) as total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $incomesByCategory = Income::where('incomes.franchise_id', $franchiseId)
            ->whereBetween('incomes.date', [$dateFrom, $dateTo])
            ->join('income_categories', 'income_categories.id', '=', 'incomes.income_category_id')
            ->selectRaw('income_categories.name as category, COALESCE(SUM(incomes.amount), 0) as total')
            ->groupBy('income_categories.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $branchPerformance = Commission::where('commissions.franchise_id', $franchiseId)
            ->whereBetween('commissions.date', [$dateFrom, $dateTo])
            ->whereIn('commissions.status', [CommissionStatus::PAGO_VALIDACION, CommissionStatus::PAGO_CONFIRMADO])
            ->join('branches', 'branches.id', '=', 'commissions.branch_id')
            ->selectRaw('branches.id, branches.name, COUNT(*) as total_commissions, COALESCE(SUM(commissions.total), 0) as total_income')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('total_income')
            ->get()
            ->toArray();

        $recentCommissions = Commission::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->with(['client:id,name', 'cadete:id,name'])
            ->orderByDesc('date')
            ->limit(15)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'date' => $c->date?->format('Y-m-d'),
                'customer' => $c->client?->name ?? 'N/A',
                'collector' => $c->cadete?->name ?? 'Sin asignar',
                'total' => round((float) $c->total, 2),
                'status' => $c->status->value ?? $c->status,
            ])
            ->toArray();

        $totalCustomers = $franchise->customers()->count();
        $activeCustomers = $franchise->customers()
            ->whereHas('commissions', fn ($q) => $q->whereBetween('date', [$dateFrom, $dateTo]))
            ->count();

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'kpis' => [
                'total_commissions' => $totalCommissions,
                'total_commission_amount' => round($totalCommissionAmount, 2),
                'total_expenses' => round($totalExpenses, 2),
                'total_incomes' => round($totalIncomes, 2),
                'matrix_debt' => round($matrixDebt, 2),
                'net_result' => round($totalCommissionAmount - $totalExpenses - $matrixDebt, 2),
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'total_branches' => $franchise->branches->count(),
                'commission_percentage' => (float) $franchise->commission_percentage_to_matrix,
            ],
            'status_breakdown' => $statusBreakdown,
            'monthly_evolution' => $evolution,
            'top_collectors' => $topCollectors,
            'expenses_by_category' => $expensesByCategory,
            'incomes_by_category' => $incomesByCategory,
            'branch_performance' => $branchPerformance,
            'recent_commissions' => $recentCommissions,
        ];
    }

    public function getSettlementReport(int $franchiseId, string $dateFrom, string $dateTo): array
    {
        $franchise = Franchise::find($franchiseId);
        if (! $franchise) {
            throw new \RuntimeException('Franquicia no encontrada');
        }

        $commissions = Commission::where('franchise_id', $franchiseId)
            ->whereIn('status', [CommissionStatus::PAGO_VALIDACION, CommissionStatus::PAGO_CONFIRMADO])
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get();

        $grossIncome = $commissions->sum('total');
        $expenses = Expense::where('franchise_id', $franchiseId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->sum('amount');

        $matrixPercentage = $franchise->commission_percentage_to_matrix;
        $matrixAmount = $grossIncome * ($matrixPercentage / 100);

        return [
            'franchise' => [
                'id' => $franchise->id,
                'name' => $franchise->name,
                'commission_percentage' => $matrixPercentage,
            ],
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'gross_income' => round($grossIncome, 2),
            'total_commissions' => $commissions->count(),
            'expenses' => round($expenses, 2),
            'matrix_amount' => round($matrixAmount, 2),
            'net_for_franchise' => round($grossIncome - $expenses - $matrixAmount, 2),
        ];
    }
}
