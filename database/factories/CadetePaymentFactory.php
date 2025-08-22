<?php

namespace Database\Factories;

use App\CadetePayment;
use App\Shared\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class CadetePaymentFactory extends Factory
{
    protected $model = CadetePayment::class;

    public function definition(): array
    {
        $paymentTypes = [
            CadetePayment::PAYMENT_TYPE_MONTHLY,
            CadetePayment::PAYMENT_TYPE_BIWEEKLY,
            CadetePayment::PAYMENT_TYPE_WEEKLY,
            CadetePayment::PAYMENT_TYPE_BONUS,
            CadetePayment::PAYMENT_TYPE_ADVANCE,
        ];

        $paymentMethods = [
            CadetePayment::PAYMENT_METHOD_CASH,
            CadetePayment::PAYMENT_METHOD_BANK_TRANSFER,
            CadetePayment::PAYMENT_METHOD_CHECK,
        ];

        $statuses = [
            CadetePayment::STATUS_PENDING,
            CadetePayment::STATUS_PAID,
            CadetePayment::STATUS_CANCELLED,
        ];

        $paymentType = $this->faker->randomElement($paymentTypes);
        $status = $this->faker->randomElement($statuses);
        
        // Generar fechas del período
        $periodStart = $this->faker->dateTimeBetween('-6 months', 'now');
        $periodEnd = clone $periodStart;
        
        // Ajustar fecha de fin según el tipo de pago
        switch ($paymentType) {
            case CadetePayment::PAYMENT_TYPE_MONTHLY:
                $periodEnd->modify('+1 month -1 day');
                break;
            case CadetePayment::PAYMENT_TYPE_BIWEEKLY:
                $periodEnd->modify('+2 weeks -1 day');
                break;
            case CadetePayment::PAYMENT_TYPE_WEEKLY:
                $periodEnd->modify('+1 week -1 day');
                break;
            default:
                $periodEnd->modify('+1 day');
        }

        $baseSalary = $this->faker->randomFloat(2, 50000, 150000);
        $commissionAmount = $this->faker->randomFloat(2, 10000, 50000);
        $bonusAmount = $this->faker->randomFloat(2, 0, 20000);
        $deductionAmount = $this->faker->randomFloat(2, 0, 15000);
        
        // Calcular monto neto
        $netAmount = $baseSalary + $commissionAmount + $bonusAmount - $deductionAmount;

        return [
            'cadete_id' => 1, // Valor por defecto, será sobrescrito por el test
            'admin_id' => 1,  // Valor por defecto, será sobrescrito por el test
            'payment_type' => $paymentType,
            'payment_method' => $this->faker->randomElement($paymentMethods),
            'base_salary' => $baseSalary,
            'commission_amount' => $commissionAmount,
            'bonus_amount' => $bonusAmount,
            'deduction_amount' => $deductionAmount,
            'net_amount' => $netAmount,
            'status' => $status,
            'payment_date' => $this->faker->dateTimeBetween($periodStart, $periodEnd),
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'description' => $this->faker->sentence(6),
            'notes' => $this->faker->optional(0.7)->paragraph(2),
            'reference_number' => $this->faker->optional(0.8)->regexify('PAY-[A-Z0-9]{8}'),
            'transaction_id' => $status === CadetePayment::STATUS_PAID ? $this->faker->regexify('TXN-[A-Z0-9]{12}') : null,
            'paid_at' => $status === CadetePayment::STATUS_PAID ? $this->faker->dateTimeBetween($periodStart, 'now') : null,
        ];
    }

    /**
     * Factory para pagos mensuales
     */
    public function monthly(): static
    {
        return $this->state(function (array $attributes) {
            $periodStart = $this->faker->dateTimeBetween('-6 months', 'now');
            $periodEnd = clone $periodStart;
            $periodEnd->modify('+1 month -1 day');
            
            return [
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
            ];
        });
    }

    /**
     * Factory para pagos quincenales
     */
    public function biweekly(): static
    {
        return $this->state(function (array $attributes) {
            $periodStart = $this->faker->dateTimeBetween('-6 months', 'now');
            $periodEnd = clone $periodStart;
            $periodEnd->modify('+2 weeks -1 day');
            
            return [
                'payment_type' => CadetePayment::PAYMENT_TYPE_BIWEEKLY,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
            ];
        });
    }

    /**
     * Factory para pagos semanales
     */
    public function weekly(): static
    {
        return $this->state(function (array $attributes) {
            $periodStart = $this->faker->dateTimeBetween('-6 months', 'now');
            $periodEnd = clone $periodStart;
            $periodEnd->modify('+1 week -1 day');
            
            return [
                'payment_type' => CadetePayment::PAYMENT_TYPE_WEEKLY,
                'period_start' => $periodStart->format('Y-m-d'),
                'period_end' => $periodEnd->format('Y-m-d'),
            ];
        });
    }

    /**
     * Factory para bonos
     */
    public function bonus(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'payment_type' => CadetePayment::PAYMENT_TYPE_BONUS,
                'base_salary' => 0,
                'bonus_amount' => $this->faker->randomFloat(2, 5000, 50000),
                'period_start' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'period_end' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            ];
        });
    }

    /**
     * Factory para pagos pendientes
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CadetePayment::STATUS_PENDING,
                'paid_at' => null,
            ];
        });
    }

    /**
     * Factory para pagos pagados
     */
    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CadetePayment::STATUS_PAID,
                'paid_at' => $this->faker->dateTimeBetween($attributes['period_start'], 'now'),
            ];
        });
    }

    /**
     * Factory para pagos cancelados
     */
    public function cancelled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => CadetePayment::STATUS_CANCELLED,
                'paid_at' => null,
            ];
        });
    }
}
