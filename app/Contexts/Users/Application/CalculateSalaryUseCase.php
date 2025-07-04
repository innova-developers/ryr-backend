<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\Commission;
use App\Shared\Models\Expense;
use App\Shared\Models\Income;
use Carbon\Carbon;

class CalculateSalaryUseCase
{
    public function __construct(
        private UserRepository $userRepository
    ) {
    }

    public function execute(int $userId, ?string $month = null): array
    {
        $user = $this->userRepository->findById($userId);

        if (! $user) {
            throw new \Exception('Usuario no encontrado');
        }

        // Si no se especifica mes, usar el mes actual
        $targetMonth = $month ? Carbon::parse($month) : Carbon::now();
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        // A: Sueldo fijo
        $baseSalary = $user->base_salary ?? 0;

        // B: Monto en comisiones
        $commissionsTotal = Commission::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('total');

        $commissionAmount = $commissionsTotal * ($user->commission_percentage / 100);

        // C: Monto en ingresos
        $incomesTotal = Income::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $incomeAmount = $incomesTotal * ($user->commission_percentage / 100);

        // Subtotal antes de gastos
        $subtotalBeforeExpenses = $baseSalary + $commissionAmount + $incomeAmount;

        // D: Gastos del usuario
        $expensesAmount = Expense::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Subtotal después de gastos
        $subtotalAfterExpenses = $subtotalBeforeExpenses - $expensesAmount;

        // Monto disponible para adelantos (B + C - D)
        $availableForAdvances = $commissionAmount + $incomeAmount - $expensesAmount;

        return [
            'user_id' => $userId,
            'month' => $targetMonth->format('Y-m'),
            'base_salary' => $baseSalary,
            'commissions_total' => $commissionsTotal,
            'commission_amount' => $commissionAmount,
            'incomes_total' => $incomesTotal,
            'income_amount' => $incomeAmount,
            'subtotal_before_expenses' => $subtotalBeforeExpenses,
            'expenses_amount' => $expensesAmount,
            'subtotal_after_expenses' => $subtotalAfterExpenses,
            'available_for_advances' => $availableForAdvances,
            'user_name' => $user->name,
            'commission_percentage' => $user->commission_percentage,
        ];
    }
}
