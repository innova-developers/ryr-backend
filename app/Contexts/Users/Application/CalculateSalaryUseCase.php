<?php

namespace App\Contexts\Users\Application;

use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Expense;
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

        $targetMonth = $month ? Carbon::parse($month) : Carbon::now();
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();

        $collectedCommissions = Commission::where('cadete_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereIn('status', [CommissionStatus::PAGO_VALIDACION, CommissionStatus::PAGO_CONFIRMADO]);

        $commissionsTotal = (clone $collectedCommissions)->sum('total');
        $commissionsCount = (clone $collectedCommissions)->count();

        $baseSalary = 0;
        $commissionAmount = 0;

        switch ($user->contract_type) {
            case 'per_pickup':
                $baseSalary = 0;
                $commissionAmount = $commissionsCount * ($user->payment_per_pickup ?? 0);

                break;
            case 'fixed_plus_commission':
                $baseSalary = $user->base_salary ?? 0;
                $commissionAmount = $commissionsTotal * ($user->commission_percentage / 100);

                break;
            case 'fixed_salary':
                $baseSalary = $user->base_salary ?? 0;
                $commissionAmount = 0;

                break;
        }

        $subtotalBeforeExpenses = $baseSalary + $commissionAmount;

        $expensesAmount = Expense::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $subtotalAfterExpenses = $subtotalBeforeExpenses - $expensesAmount;

        $availableForAdvances = $commissionAmount - $expensesAmount;

        return [
            'user_id' => $userId,
            'month' => $targetMonth->format('Y-m'),
            'contract_type' => $user->contract_type,
            'base_salary' => $baseSalary,
            'commissions_total' => $commissionsTotal,
            'commissions_count' => $commissionsCount,
            'commission_amount' => $commissionAmount,
            'payment_per_pickup' => $user->payment_per_pickup ?? 0,
            'subtotal_before_expenses' => $subtotalBeforeExpenses,
            'expenses_amount' => $expensesAmount,
            'subtotal_after_expenses' => $subtotalAfterExpenses,
            'available_for_advances' => $availableForAdvances,
            'user_name' => $user->name,
            'commission_percentage' => $user->commission_percentage,
        ];
    }
}
