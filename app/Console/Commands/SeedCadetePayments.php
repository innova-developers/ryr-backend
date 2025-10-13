<?php

namespace App\Console\Commands;

use App\CadetePayment;
use App\Shared\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SeedCadetePayments extends Command
{
    protected $signature = 'seed:cadete-payments {--cadete-id= : ID específico del cadete} {--months=3 : Número de meses de datos a generar}';
    protected $description = 'Generar datos de prueba para pagos de cadetes';

    public function handle()
    {
        $cadeteId = $this->option('cadete-id');
        $months = (int) $this->option('months');

        // Obtener cadetes
        if ($cadeteId) {
            $cadetes = User::where('id', $cadeteId)->where('role', 'cadete')->get();
        } else {
            $cadetes = User::where('role', 'cadete')->get();
        }

        if ($cadetes->isEmpty()) {
            $this->error('No se encontraron cadetes. Creando uno de prueba...');
            $cadete = User::create([
                'name' => 'Cadete Demo',
                'email' => 'cadete@demo.com',
                'password' => bcrypt('password'),
                'role' => 'cadete'
            ]);
            $cadetes = collect([$cadete]);
        }

        $this->info("Generando pagos para {$cadetes->count()} cadete(s) de los últimos {$months} meses...");

        $totalCreated = 0;

        foreach ($cadetes as $cadete) {
            $this->info("Generando pagos para cadete: {$cadete->name} (ID: {$cadete->id})");
            
            $created = $this->generatePaymentsForCadete($cadete, $months);
            $totalCreated += $created;
            
            $this->line("  ✅ Creados {$created} pagos");
        }

        $this->info("\n🎉 Total de pagos creados: {$totalCreated}");
    }

    private function generatePaymentsForCadete(User $cadete, int $months): int
    {
        $created = 0;
        $startDate = now()->subMonths($months)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Generar pagos mensuales
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addMonth()) {
            $baseSalary = 50000 + rand(-5000, 10000); // Variación en salario base
            $commissionAmount = rand(8000, 25000); // Comisiones variables
            $bonusAmount = rand(0, 15000); // Bonos ocasionales
            $deductionAmount = rand(0, 5000); // Deducciones ocasionales
            $netAmount = $baseSalary + $commissionAmount + $bonusAmount - $deductionAmount;

            // Determinar estado basado en la fecha
            $status = $this->determineStatus($date);
            $paymentDate = $this->determinePaymentDate($date, $status);

            $payment = CadetePayment::create([
                'cadete_id' => $cadete->id,
                'admin_id' => 1, // Asumiendo admin ID 1
                'payment_type' => 'monthly',
                'payment_method' => $this->getRandomPaymentMethod(),
                'base_salary' => $baseSalary,
                'commission_amount' => $commissionAmount,
                'bonus_amount' => $bonusAmount,
                'deduction_amount' => $deductionAmount,
                'net_amount' => $netAmount,
                'status' => $status,
                'payment_date' => $paymentDate,
                'period_start' => $date->copy()->startOfMonth(),
                'period_end' => $date->copy()->endOfMonth(),
                'description' => "Pago mensual {$date->format('F Y')}",
                'paid_at' => $status === 'paid' ? $paymentDate : null,
            ]);

            $created++;

            // Generar pagos quincenales ocasionales
            if (rand(1, 3) === 1) { // 33% de probabilidad
                $this->createBiweeklyPayment($cadete, $date);
                $created++;
            }

            // Generar bonos ocasionales
            if (rand(1, 4) === 1) { // 25% de probabilidad
                $this->createBonusPayment($cadete, $date);
                $created++;
            }
        }

        return $created;
    }

    private function determineStatus(Carbon $date): string
    {
        $daysDiff = now()->diffInDays($date, false);
        
        if ($daysDiff > 30) {
            return rand(1, 10) === 1 ? 'cancelled' : 'paid'; // 10% cancelados para fechas lejanas
        } elseif ($daysDiff > 0) {
            return rand(1, 3) === 1 ? 'pending' : 'paid'; // 33% pendientes para fechas recientes
        } else {
            return 'pending'; // Fechas futuras siempre pendientes
        }
    }

    private function determinePaymentDate(Carbon $date, string $status): Carbon
    {
        if ($status === 'paid') {
            return $date->copy()->addDays(rand(0, 5)); // Pagado entre 0-5 días después
        } elseif ($status === 'cancelled') {
            return $date->copy()->addDays(rand(1, 3)); // Cancelado 1-3 días después
        } else {
            return $date->copy()->addDays(rand(0, 2)); // Pendiente 0-2 días después
        }
    }

    private function getRandomPaymentMethod(): string
    {
        $methods = ['bank_transfer', 'cash', 'check'];
        return $methods[array_rand($methods)];
    }

    private function createBiweeklyPayment(User $cadete, Carbon $date): void
    {
        $baseSalary = 25000 + rand(-2000, 5000);
        $commissionAmount = rand(3000, 12000);
        $netAmount = $baseSalary + $commissionAmount;

        CadetePayment::create([
            'cadete_id' => $cadete->id,
            'admin_id' => 1,
            'payment_type' => 'biweekly',
            'payment_method' => $this->getRandomPaymentMethod(),
            'base_salary' => $baseSalary,
            'commission_amount' => $commissionAmount,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => $netAmount,
            'status' => $this->determineStatus($date),
            'payment_date' => $date->copy()->addDays(15),
            'period_start' => $date->copy()->addDays(1),
            'period_end' => $date->copy()->addDays(15),
            'description' => "Pago quincenal {$date->format('M Y')}",
        ]);
    }

    private function createBonusPayment(User $cadete, Carbon $date): void
    {
        $bonusAmount = rand(5000, 20000);
        $status = rand(1, 5) === 1 ? 'cancelled' : 'paid'; // 20% cancelados

        CadetePayment::create([
            'cadete_id' => $cadete->id,
            'admin_id' => 1,
            'payment_type' => 'bonus',
            'payment_method' => 'bank_transfer',
            'base_salary' => 0,
            'commission_amount' => 0,
            'bonus_amount' => $bonusAmount,
            'deduction_amount' => 0,
            'net_amount' => $bonusAmount,
            'status' => $status,
            'payment_date' => $date->copy()->addDays(rand(1, 10)),
            'period_start' => $date->copy(),
            'period_end' => $date->copy(),
            'description' => "Bono por rendimiento {$date->format('M Y')}",
            'paid_at' => $status === 'paid' ? $date->copy()->addDays(rand(1, 10)) : null,
        ]);
    }
}