<?php

namespace Tests\Feature\Cadete;

use App\CadetePayment;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CadetePaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $cadete;
    protected User $admin;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear sucursal
        $this->branch = Branch::factory()->create();

        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
            'commission_percentage' => 20.0,
        ]);

        // Crear administrador
        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
            'branch_id' => $this->branch->id,
        ]);

        Sanctum::actingAs($this->cadete);
    }

    /** @test */
    public function cadete_can_view_own_payments()
    {
        // Crear pagos para el cadete
        CadetePayment::factory()->count(3)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
        ]);

        // Crear pagos para otro cadete (no deberían aparecer)
        $otherCadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        CadetePayment::factory()->count(2)->create([
            'cadete_id' => $otherCadete->id,
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/cadete/payments');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id', 'cadete_id', 'admin_id', 'payment_type', 'payment_method',
                    'net_amount', 'status', 'payment_date', 'period_start', 'period_end',
                ],
            ],
            'pagination',
            'filters',
        ]);

        // Solo debería ver sus propios pagos
        $this->assertCount(3, $response->json('data'));

        // Verificar que todos los pagos son del cadete autenticado
        foreach ($response->json('data') as $payment) {
            $this->assertEquals($this->cadete->id, $payment['cadete_id']);
        }
    }

    /** @test */
    public function cadete_can_view_specific_own_payment()
    {
        $payment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto',
        ]);

        // Debug: verificar que el pago se creó correctamente
        \Log::info('Test - payment->id: ' . $payment->id);
        \Log::info('Test - payment->cadete_id: ' . $payment->cadete_id);
        \Log::info('Test - this->cadete->id: ' . $this->cadete->id);

        // Verificar en la base de datos
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'cadete_id' => $this->cadete->id,
        ]);

        $response = $this->getJson("/api/cadete/payments/{$payment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'cadete_id' => $this->cadete->id,
            ],
        ]);
    }

    /** @test */
    public function cadete_cannot_view_other_cadete_payment()
    {
        $otherCadete = User::factory()->create([
            'role' => UserRole::CADETE,
            'branch_id' => $this->branch->id,
        ]);

        $payment = CadetePayment::factory()->create([
            'cadete_id' => $otherCadete->id,
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/cadete/payments/{$payment->id}");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'No tienes acceso a este pago',
        ]);
    }

    /** @test */
    public function cadete_can_get_payments_summary()
    {
        // Crear pagos pendientes con fechas únicas
        for ($i = 0; $i < 2; $i++) {
            $date = now()->subYears($i + 1);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago pendiente {$i}",
            ]);
        }

        // Crear pagos pagados con fechas únicas
        for ($i = 0; $i < 3; $i++) {
            $date = now()->subYears($i + 3);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PAID,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago pagado {$i}",
            ]);
        }

        // Crear un pago cancelado con fecha única
        $date = now()->subYears(6);
        CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_CANCELLED,
            'payment_date' => $date->format('Y-m-d'),
            'period_start' => $date->startOfMonth()->format('Y-m-d'),
            'period_end' => $date->endOfMonth()->format('Y-m-d'),
            'description' => "Pago cancelado",
        ]);

        $response = $this->getJson('/api/cadete/payments/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_payments', 'total_amount', 'pending_payments', 'pending_amount',
                'paid_payments', 'paid_amount', 'cancelled_payments', 'cancelled_amount',
                'by_payment_type',
            ],
        ]);

        $data = $response->json('data');
        $this->assertEquals(6, $data['total_payments']);
        $this->assertEquals(2, $data['pending_payments']);
        $this->assertEquals(3, $data['paid_payments']);
        $this->assertEquals(1, $data['cancelled_payments']);
    }

    /** @test */
    public function cadete_can_get_next_payment()
    {
        // Crear un pago pendiente para el futuro con fecha única
        $date = now()->subYears(1);
        $nextPayment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => now()->addDays(5)->format('Y-m-d'),
            'period_start' => $date->startOfMonth()->format('Y-m-d'),
            'period_end' => $date->endOfMonth()->format('Y-m-d'),
            'description' => "Pago pendiente futuro",
        ]);

        // Crear un pago pagado (no debería aparecer) con fecha única
        $date2 = now()->subYears(2);
        CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PAID,
            'payment_date' => $date2->format('Y-m-d'),
            'period_start' => $date2->startOfMonth()->format('Y-m-d'),
            'period_end' => $date2->endOfMonth()->format('Y-m-d'),
            'description' => "Pago pagado",
        ]);

        $response = $this->getJson('/api/cadete/payments/next-payment');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $nextPayment->id,
                'status' => CadetePayment::STATUS_PENDING,
            ],
        ]);
    }

    /** @test */
    public function cadete_gets_null_when_no_pending_payments()
    {
        // Solo crear pagos pagados con fechas únicas
        for ($i = 0; $i < 3; $i++) {
            $date = now()->subYears($i + 1);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PAID,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago pagado {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments/next-payment');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'No hay pagos pendientes',
            'data' => null,
        ]);
    }

    /** @test */
    public function cadete_can_get_recent_payments()
    {
        // Crear varios pagos con fechas únicas
        for ($i = 0; $i < 5; $i++) {
            $date = now()->subYears($i + 1);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments/recent?limit=3');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id', 'payment_type', 'payment_method', 'net_amount', 'status',
                    'payment_date', 'period_start', 'period_end',
                ],
            ],
        ]);

        // Debería devolver solo 3 pagos (el límite)
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function cadete_can_filter_payments_by_payment_type()
    {
        // Crear pagos mensuales con fechas únicas
        for ($i = 0; $i < 2; $i++) {
            $date = now()->subYears($i + 1);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual {$i}",
            ]);
        }

        // Crear pagos de bonificación con fechas únicas
        for ($i = 0; $i < 3; $i++) {
            $date = now()->subYears($i + 3);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_BONUS,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 0,
                'commission_amount' => 0,
                'bonus_amount' => 10000.00,
                'deduction_amount' => 0,
                'net_amount' => 10000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago bonificación {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments?payment_type=monthly');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function cadete_can_filter_payments_by_status()
    {
        // Crear pagos pendientes con fechas únicas
        for ($i = 0; $i < 2; $i++) {
            $date = now()->subYears($i + 1);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago pendiente {$i}",
            ]);
        }

        // Crear pagos pagados con fechas únicas
        for ($i = 0; $i < 3; $i++) {
            $date = now()->subYears($i + 3);
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PAID,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago pagado {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments?status=pending');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function cadete_can_filter_payments_by_date_range()
    {
        // Crear pagos en diferentes fechas con datos específicos
        $oldPayment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => now()->subMonths(3)->format('Y-m-d'),
            'period_start' => now()->subMonths(3)->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->subMonths(3)->endOfMonth()->format('Y-m-d'),
            'description' => 'Pago mensual antiguo',
        ]);

        $recentPayment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
            'description' => 'Pago mensual reciente',
        ]);

        $response = $this->getJson('/api/cadete/payments?date_from=' . now()->subMonth()->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'));

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($recentPayment->id, $response->json('data.0.id'));
    }

    /** @test */
    public function cadete_can_get_payments_with_pagination()
    {
        // Crear más pagos de los que caben en una página con datos específicos
        for ($i = 0; $i < 25; $i++) {
            $date = now()->subYears($i + 1); // Usar años completamente diferentes
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments?page=2&per_page=10');

        $response->assertStatus(200);

        $pagination = $response->json('pagination');
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['last_page']);
    }

    /** @test */
    public function cadete_can_get_summary_with_date_filter()
    {
        // Crear pagos en diferentes fechas con datos específicos
        for ($i = 0; $i < 2; $i++) {
            $date = now()->subYears($i + 2); // Usar años completamente diferentes
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual antiguo {$i}",
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            $date = now()->subYears($i + 3); // Usar años completamente diferentes
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_BIWEEKLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_BANK_TRANSFER,
                'base_salary' => 25000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 25000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfWeek()->format('Y-m-d'),
                'period_end' => $date->endOfWeek()->format('Y-m-d'),
                'description' => "Pago quincenal reciente {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments/summary?date_from=' . now()->subYears(3)->format('Y-m-d') . '&date_to=' . now()->subYears(2)->format('Y-m-d'));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_payments']); // Solo los pagos recientes
    }

    /** @test */
    public function cadete_can_get_recent_payments_with_default_limit()
    {
        // Crear varios pagos con datos específicos para evitar conflictos
        for ($i = 0; $i < 15; $i++) {
            $date = now()->subYears($i + 1); // Usar años completamente diferentes
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
                'base_salary' => 50000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 50000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfMonth()->format('Y-m-d'),
                'period_end' => $date->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments/recent');

        $response->assertStatus(200);

        // Por defecto debería devolver 10 pagos
        $this->assertCount(10, $response->json('data'));
    }

    /** @test */
    public function cadete_can_get_recent_payments_with_custom_limit()
    {
        // Crear varios pagos con datos específicos para evitar conflictos
        for ($i = 0; $i < 20; $i++) {
            $date = now()->subYears($i + 1); // Usar años completamente diferentes
            CadetePayment::create([
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id,
                'payment_type' => CadetePayment::PAYMENT_TYPE_BIWEEKLY,
                'payment_method' => CadetePayment::PAYMENT_METHOD_BANK_TRANSFER,
                'base_salary' => 25000.00,
                'commission_amount' => 0,
                'bonus_amount' => 0,
                'deduction_amount' => 0,
                'net_amount' => 25000.00,
                'status' => CadetePayment::STATUS_PENDING,
                'payment_date' => $date->format('Y-m-d'),
                'period_start' => $date->startOfWeek()->format('Y-m-d'),
                'period_end' => $date->endOfWeek()->format('Y-m-d'),
                'description' => "Pago quincenal {$i}",
            ]);
        }

        $response = $this->getJson('/api/cadete/payments/recent?limit=15');

        $response->assertStatus(200);

        // Debería devolver 15 pagos
        $this->assertCount(15, $response->json('data'));
    }

    /** @test */
    public function cadete_cannot_access_admin_payment_endpoints()
    {
        $payment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto',
        ]);

        // Intentar crear un pago (solo admin puede)
        $response = $this->postJson('/api/admin/cadete-payments', []);
        $response->assertStatus(403); // Prohibido para cadetes

        // Intentar actualizar un pago (solo admin puede)
        $response = $this->putJson("/api/admin/cadete-payments/{$payment->id}", []);
        $response->assertStatus(403); // Prohibido para cadetes

        // Intentar eliminar un pago (solo admin puede)
        $response = $this->deleteJson("/api/admin/cadete-payments/{$payment->id}");
        $response->assertStatus(403); // Prohibido para cadetes
    }

    /** @test */
    public function cadete_payments_are_ordered_by_payment_date_desc()
    {
        // Crear pagos en diferentes fechas
        $oldPayment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => now()->subDays(5)->format('Y-m-d'),
            'period_start' => now()->subDays(5)->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->subDays(5)->endOfMonth()->format('Y-m-d'),
            'description' => 'Pago mensual antiguo',
        ]);

        $recentPayment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_BIWEEKLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_BANK_TRANSFER,
            'base_salary' => 25000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 25000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfWeek()->format('Y-m-d'),
            'period_end' => now()->endOfWeek()->format('Y-m-d'),
            'description' => 'Pago quincenal reciente',
        ]);

        $response = $this->getJson('/api/cadete/payments');

        $response->assertStatus(200);

        $data = $response->json('data');
        // El primer pago debería ser el más reciente
        $this->assertEquals($recentPayment->id, $data[0]['id']);
        $this->assertEquals($oldPayment->id, $data[1]['id']);
    }

    /** @test */
    public function cadete_payments_include_admin_information()
    {
        $payment = CadetePayment::create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'commission_amount' => 0,
            'bonus_amount' => 0,
            'deduction_amount' => 0,
            'net_amount' => 50000.00,
            'status' => CadetePayment::STATUS_PENDING,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto',
        ]);

        $response = $this->getJson("/api/cadete/payments/{$payment->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayHasKey('admin', $data);
        $this->assertEquals($this->admin->id, $data['admin']['id']);
        $this->assertEquals($this->admin->name, $data['admin']['name']);
        $this->assertEquals($this->admin->email, $data['admin']['email']);
    }

    /** @test */
    public function cadete_payments_filters_include_available_options()
    {
        $response = $this->getJson('/api/cadete/payments');

        $response->assertStatus(200);

        $filters = $response->json('filters');
        $this->assertArrayHasKey('payment_types', $filters);
        $this->assertArrayHasKey('statuses', $filters);

        // Verificar que incluye los tipos de pago disponibles
        $this->assertArrayHasKey('monthly', $filters['payment_types']);
        $this->assertArrayHasKey('biweekly', $filters['payment_types']);
        $this->assertArrayHasKey('weekly', $filters['payment_types']);
        $this->assertArrayHasKey('bonus', $filters['payment_types']);

        // Verificar que incluye los estados disponibles
        $this->assertArrayHasKey('pending', $filters['statuses']);
        $this->assertArrayHasKey('paid', $filters['statuses']);
        $this->assertArrayHasKey('cancelled', $filters['statuses']);
    }
}
