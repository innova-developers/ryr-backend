<?php

namespace Tests\Feature\Admin;

use App\CadetePayment;
use App\Shared\Models\User;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\Commission;
use App\Shared\Enums\UserRole;
use App\Shared\Enums\CommissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CadetePaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cadete;
    protected Branch $branch;
    protected Customer $client;
    protected Destination $destination;
    protected Location $originLocation;
    protected Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear sucursal
        $this->branch = Branch::factory()->create();

        // Crear administrador
        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
            'branch_id' => $this->branch->id
        ]);

        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE->value,
            'branch_id' => $this->branch->id,
            'commission_percentage' => 25.0
        ]);



        // Crear cliente
        $this->client = Customer::factory()->create();

        // Crear ubicaciones
        $this->originLocation = Location::factory()->create([
            'name' => 'Almacén Central',
            'origin' => 'CABA'
        ]);

        $this->destinationLocation = Location::factory()->create([
            'name' => 'Oficina Norte',
            'origin' => 'Zona Norte'
        ]);

        // Crear destino
        $this->destination = Destination::factory()->create([
            'origin' => 'CABA',
            'destination' => 'Zona Norte'
        ]);

        Sanctum::actingAs($this->admin);
    }

    /** @test */
    public function admin_can_list_all_cadete_payments()
    {
        // Crear algunos pagos de prueba
        CadetePayment::factory()->count(3)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id
        ]);

        $response = $this->getJson('/api/admin/cadete-payments');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id', 'cadete_id', 'admin_id', 'payment_type', 'payment_method',
                    'commission_amount', 'base_salary', 'bonus_amount',
                    'deduction_amount', 'net_amount', 'status', 'payment_date',
                    'period_start', 'period_end', 'description', 'notes',
                    'reference_number', 'transaction_id', 'paid_at', 'created_at', 'updated_at',
                    'cadete' => ['id', 'name', 'email'],
                    'admin' => ['id', 'name', 'email']
                ]
            ],
            'pagination',
            'filters'
        ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function admin_can_create_cadete_payment()
    {
        // Crear comisión entregada para calcular ganancias
        Commission::factory()->create([
            'cadete_id' => $this->cadete->id,
            'client_id' => $this->client->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1000.00,
            'date' => now()
        ]);

        $paymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_BANK_TRANSFER,
            'base_salary' => 80000.00,
            'bonus_amount' => 10000.00,
            'deduction_amount' => 5000.00,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
            'description' => 'Pago mensual de agosto',
            'notes' => 'Incluye bono por productividad',
            'reference_number' => 'PAY-2025-08-001'
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id', 'cadete_id', 'admin_id', 'payment_type', 'payment_method',
                'base_salary', 'bonus_amount', 'deduction_amount', 'net_amount',
                'status', 'payment_date', 'period_start', 'period_end'
            ]
        ]);

        // Verificar que se calculó correctamente la comisión (25% de $1000 = $250)
        $this->assertEquals(250.00, $response->json('data.commission_amount'));
        
        // Verificar que se calculó correctamente el monto neto
        $expectedNetAmount = 80000 + 250 + 10000 - 5000; // base + comisión + bono - deducción
        $this->assertEquals($expectedNetAmount, $response->json('data.net_amount'));

        // Verificar que se guardó en la base de datos
        $this->assertDatabaseHas('cadete_payments', [
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'status' => CadetePayment::STATUS_PENDING
        ]);
    }

    /** @test */
    public function admin_cannot_create_payment_for_non_cadete_user()
    {
        $nonCadeteUser = User::factory()->create([
            'role' => UserRole::CLIENTE->value,
            'branch_id' => $this->branch->id
        ]);

        $paymentData = [
            'cadete_id' => $nonCadeteUser->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'El usuario especificado no es un cadete'
        ]);
    }

    /** @test */
    public function admin_cannot_create_duplicate_payment_for_same_period()
    {
        // Crear un pago inicial usando el controlador
        $firstPaymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 50000.00,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto'
        ];

        $firstResponse = $this->postJson('/api/admin/cadete-payments', $firstPaymentData);
        $firstResponse->assertStatus(201);

        // Verificar que el primer pago se creó correctamente
        $this->assertDatabaseHas('cadete_payments', [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'period_start' => '2025-08-01 00:00:00',
            'period_end' => '2025-08-31 00:00:00',
        ]);

        // Verificar que existe exactamente un pago para este período
        $existingPayments = CadetePayment::where('cadete_id', $this->cadete->id)
            ->where('payment_type', CadetePayment::PAYMENT_TYPE_MONTHLY)
            ->where('period_start', '2025-08-01 00:00:00')
            ->where('period_end', '2025-08-31 00:00:00')
            ->count();
        
        $this->assertEquals(1, $existingPayments, 'Debe existir exactamente un pago para este período');

        // Intentar crear otro pago para el mismo período
        $duplicatePaymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'base_salary' => 60000.00,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $duplicatePaymentData);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Ya existe un pago para este cadete en el período especificado'
        ]);
    }

    /** @test */
    public function admin_can_view_specific_payment()
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
            'description' => 'Pago mensual agosto'
        ]);

        $response = $this->getJson("/api/admin/cadete-payments/{$payment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'cadete_id' => $this->cadete->id,
                'admin_id' => $this->admin->id
            ]
        ]);
    }

    /** @test */
    public function admin_can_update_payment()
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
            'description' => 'Pago mensual agosto'
        ]);

        $updateData = [
            'base_salary' => 90000.00,
            'bonus_amount' => 15000.00,
            'description' => 'Pago actualizado'
        ];

        $response = $this->putJson("/api/admin/cadete-payments/{$payment->id}", $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pago actualizado exitosamente'
        ]);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'base_salary' => 90000.00,
            'bonus_amount' => 15000.00,
            'description' => 'Pago actualizado'
        ]);
    }

    /** @test */
    public function admin_cannot_update_paid_payment()
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
            'status' => CadetePayment::STATUS_PAID,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto',
            'paid_at' => now()
        ]);

        $updateData = [
            'base_salary' => 90000.00
        ];

        $response = $this->putJson("/api/admin/cadete-payments/{$payment->id}", $updateData);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'No se puede editar un pago que ya ha sido pagado'
        ]);
    }

    /** @test */
    public function admin_can_delete_pending_payment()
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
            'description' => 'Pago mensual agosto'
        ]);

        $response = $this->deleteJson("/api/admin/cadete-payments/{$payment->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pago eliminado exitosamente'
        ]);

        // Verificar que se marcó como eliminado (SoftDeletes)
        $this->assertSoftDeleted('cadete_payments', [
            'id' => $payment->id
        ]);
    }

    /** @test */
    public function admin_cannot_delete_paid_payment()
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
            'status' => CadetePayment::STATUS_PAID,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto',
            'paid_at' => now()
        ]);

        $response = $this->deleteJson("/api/admin/cadete-payments/{$payment->id}");

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'No se puede eliminar un pago que ya ha sido pagado'
        ]);
    }

    /** @test */
    public function admin_can_mark_payment_as_paid()
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
            'description' => 'Pago mensual agosto'
        ]);

        $response = $this->patchJson("/api/admin/cadete-payments/{$payment->id}/mark-as-paid");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pago marcado como pagado exitosamente'
        ]);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'status' => CadetePayment::STATUS_PAID
        ]);
    }

    /** @test */
    public function admin_can_mark_payment_as_paid_with_proof_file()
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
            'description' => 'Pago mensual agosto'
        ]);

        // Crear un archivo de prueba
        $file = \Illuminate\Http\UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf');

        $response = $this->patchJson("/api/admin/cadete-payments/{$payment->id}/mark-as-paid", [
            'receipt' => $file,
            'transaction_id' => 'TXN123456',
            'notes' => 'Pago realizado por transferencia bancaria'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pago marcado como pagado exitosamente con comprobante adjunto'
        ]);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'status' => CadetePayment::STATUS_PAID,
            'payment_proof_filename' => 'comprobante.pdf',
            'payment_proof_mime_type' => 'application/pdf'
        ]);

        // Verificar que el archivo se guardó
        $this->assertNotNull($payment->fresh()->payment_proof_path);

        // Verificar que se actualizaron los campos adicionales
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'transaction_id' => 'TXN123456',
            'notes' => 'Pago realizado por transferencia bancaria'
        ]);
    }

    /** @test */
    public function admin_cannot_mark_payment_as_paid_with_invalid_file()
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
            'description' => 'Pago mensual agosto'
        ]);

        // Crear un archivo de prueba con tipo inválido
        $file = \Illuminate\Http\UploadedFile::fake()->create('documento.txt', 100, 'text/plain');

        $response = $this->patchJson("/api/admin/cadete-payments/{$payment->id}/mark-as-paid", [
            'receipt' => $file
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['receipt']);

        // Verificar que el pago no se marcó como pagado
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'status' => CadetePayment::STATUS_PENDING
        ]);
    }

    /** @test */
    public function admin_can_download_payment_proof()
    {
        // Crear un archivo real para el test
        $testFilePath = storage_path('app/public/payment_proofs/test_file.pdf');
        $testFileDir = dirname($testFilePath);
        
        if (!is_dir($testFileDir)) {
            mkdir($testFileDir, 0755, true);
        }
        
        // Crear un archivo PDF simple
        file_put_contents($testFilePath, '%PDF-1.4 test file');

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
            'status' => CadetePayment::STATUS_PAID,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto',
            'payment_proof_filename' => 'comprobante.pdf',
            'payment_proof_path' => 'payment_proofs/test_file.pdf',
            'payment_proof_mime_type' => 'application/pdf',
            'payment_proof_size' => 1024,
            'payment_proof_uploaded_at' => now(),
        ]);

        $response = $this->getJson("/api/admin/cadete-payments/{$payment->id}/download-proof");

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=comprobante.pdf');

        // Limpiar el archivo de prueba
        if (file_exists($testFilePath)) {
            unlink($testFilePath);
        }
    }

    /** @test */
    public function admin_cannot_download_payment_proof_for_payment_without_proof()
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
            'status' => CadetePayment::STATUS_PAID,
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual agosto'
        ]);

        $response = $this->getJson("/api/admin/cadete-payments/{$payment->id}/download-proof");

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Este pago no tiene comprobante de pago'
        ]);
    }

    /** @test */
    public function admin_can_mark_payment_as_cancelled()
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
            'description' => 'Pago mensual agosto'
        ]);

        $response = $this->patchJson("/api/admin/cadete-payments/{$payment->id}/mark-as-cancelled");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pago cancelado exitosamente'
        ]);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('cadete_payments', [
            'id' => $payment->id,
            'status' => CadetePayment::STATUS_CANCELLED
        ]);
    }

    /** @test */
    public function admin_can_get_payments_by_cadete()
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
            'description' => 'Pago mensual agosto'
        ]);

        $response = $this->getJson("/api/admin/cadete-payments/cadete/{$this->cadete->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'cadete_id',
                    'admin_id',
                    'payment_type',
                    'payment_method',
                    'base_salary',
                    'commission_amount',
                    'bonus_amount',
                    'deduction_amount',
                    'net_amount',
                    'status',
                    'payment_date',
                    'period_start',
                    'period_end',
                    'description',
                    'notes',
                    'reference_number',
                    'transaction_id',
                    'paid_at',
                    'created_at',
                    'updated_at',
                ]
            ],
            'pagination',
            'cadete'
        ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($payment->id, $response->json('data.0.id'));
    }

    /** @test */
    public function admin_can_get_payments_summary()
    {
        // Crear pagos con diferentes estados
        CadetePayment::factory()->count(2)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'status' => CadetePayment::STATUS_PENDING
        ]);

        CadetePayment::factory()->count(3)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'status' => CadetePayment::STATUS_PAID
        ]);

        CadetePayment::factory()->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'status' => CadetePayment::STATUS_CANCELLED
        ]);

        $response = $this->getJson('/api/admin/cadete-payments/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_payments', 'total_amount', 'pending_payments', 'pending_amount',
                'paid_payments', 'paid_amount', 'cancelled_payments', 'cancelled_amount',
                'by_payment_type'
            ]
        ]);

        $data = $response->json('data');
        $this->assertEquals(6, $data['total_payments']);
        $this->assertEquals(2, $data['pending_payments']);
        $this->assertEquals(3, $data['paid_payments']);
        $this->assertEquals(1, $data['cancelled_payments']);
    }

    /** @test */
    public function admin_can_filter_payments_by_cadete()
    {
        $otherCadete = User::factory()->create([
            'role' => UserRole::CADETE->value,
            'branch_id' => $this->branch->id
        ]);

        // Crear pagos para ambos cadetes
        CadetePayment::factory()->count(2)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id
        ]);

        CadetePayment::factory()->count(3)->create([
            'cadete_id' => $otherCadete->id,
            'admin_id' => $this->admin->id
        ]);

        $response = $this->getJson("/api/admin/cadete-payments?cadete_id={$this->cadete->id}");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function admin_can_filter_payments_by_status()
    {
        // Crear pagos con fechas únicas para evitar conflictos de constraint
        for ($i = 0; $i < 2; $i++) {
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
                'payment_date' => now()->subYears($i + 1)->format('Y-m-d'),
                'period_start' => now()->subYears($i + 1)->startOfMonth()->format('Y-m-d'),
                'period_end' => now()->subYears($i + 1)->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual {$i}"
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
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
                'payment_date' => now()->subYears($i + 4)->format('Y-m-d'),
                'period_start' => now()->subYears($i + 4)->startOfMonth()->format('Y-m-d'),
                'period_end' => now()->subYears($i + 4)->endOfMonth()->format('Y-m-d'),
                'description' => "Pago mensual pagado {$i}"
            ]);
        }

        $response = $this->getJson('/api/admin/cadete-payments?status=pending');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function admin_can_filter_payments_by_payment_type()
    {
        CadetePayment::factory()->count(2)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY
        ]);

        CadetePayment::factory()->count(3)->create([
            'cadete_id' => $this->cadete->id,
            'admin_id' => $this->admin->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_BONUS
        ]);

        $response = $this->getJson('/api/admin/cadete-payments?payment_type=monthly');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function admin_can_filter_payments_by_date_range()
    {
        // Crear un pago reciente
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
            'payment_date' => '2025-08-15',
            'period_start' => '2025-08-01',
            'period_end' => '2025-08-31',
            'description' => 'Pago mensual actual'
        ]);

        $response = $this->getJson('/api/admin/cadete-payments?date_from=2025-07-01&date_to=2025-08-31');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($recentPayment->id, $response->json('data.0.id'));
    }

    /** @test */
    public function payment_validation_requires_cadete_id()
    {
        $paymentData = [
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cadete_id']);
    }

    /** @test */
    public function payment_validation_requires_payment_type()
    {
        $paymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_type']);
    }

    /** @test */
    public function payment_validation_requires_payment_method()
    {
        $paymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    /** @test */
    public function payment_validation_requires_payment_date()
    {
        $paymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'period_start' => now()->startOfMonth()->format('Y-m-d'),
            'period_end' => now()->endOfMonth()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_date']);
    }

    /** @test */
    public function payment_validation_requires_period_dates()
    {
        $paymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'payment_date' => now()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['period_start', 'period_end']);
    }

    /** @test */
    public function payment_validation_period_end_must_be_after_or_equal_to_period_start()
    {
        $paymentData = [
            'cadete_id' => $this->cadete->id,
            'payment_type' => CadetePayment::PAYMENT_TYPE_MONTHLY,
            'payment_method' => CadetePayment::PAYMENT_METHOD_CASH,
            'payment_date' => now()->format('Y-m-d'),
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->subDay()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/admin/cadete-payments', $paymentData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['period_end']);
    }

    /** @test */
    public function admin_can_calculate_cadete_payment()
    {
        $testDate = '2025-08-22';
        
        // Crear comisiones para el cadete en el período especificado
        // Usar create() directamente para evitar problemas con el factory
        for ($i = 0; $i < 5; $i++) {
            Commission::create([
                'client_id' => 1,
                'destination_id' => 1,
                'branch_id' => 1,
                'user_id' => 1,
                'origin_location_id' => 1,
                'destination_location_id' => 1,
                'cadete_id' => $this->cadete->id,
                'status' => CommissionStatus::ENTREGADO->value,
                'total' => 1000.00,
                'date' => $testDate
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            Commission::create([
                'client_id' => 1,
                'destination_id' => 1,
                'branch_id' => 1,
                'user_id' => 1,
                'origin_location_id' => 1,
                'destination_location_id' => 1,
                'cadete_id' => $this->cadete->id,
                'status' => CommissionStatus::CADETE_ASIGNADO->value,
                'total' => 500.00,
                'date' => $testDate
            ]);
        }

        // Debug: verificar que las comisiones se crearon
        \Log::info('Commissions created:', [
            'total_commissions_in_db' => Commission::where('cadete_id', $this->cadete->id)->count(),
            'commissions_with_date' => Commission::where('cadete_id', $this->cadete->id)->where('date', $testDate)->count(),
            'all_commissions_dates' => Commission::where('cadete_id', $this->cadete->id)->pluck('date')->toArray(),
            'test_date' => $testDate,
            'test_date_type' => gettype($testDate)
        ]);

        $requestData = [
            'cadete_id' => $this->cadete->id,
            'period_start' => $testDate,
            'period_end' => $testDate,
            'base_salary' => 50000,
            'commission_percentage' => 15,
            'income_percentage' => 10
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $contractType = $data['cadete_info']['contract_type'];
        
        // Verificar estructura base
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'cadete_info' => ['id', 'name', 'contract_type', 'contract_type_label'],
                'period_info' => ['period_start', 'period_end', 'days_count'],
                'commissions_summary' => [
                    'total_commissions', 'delivered_commissions', 'pending_commissions',
                    'total_commission_value', 'commission_amount'
                ],
                'calculated_amounts' => [
                    'gross_total', 'net_amount'
                ]
            ]
        ]);
        
        // Verificar campos específicos según el tipo de contratación
        if ($contractType === 'fixed_salary') {
            $response->assertJsonStructure([
                'data' => [
                    'cadete_info' => ['id', 'name', 'contract_type', 'contract_type_label', 'base_salary'],
                    'calculated_amounts' => ['base_salary', 'commission_amount', 'gross_total', 'net_amount']
                ]
            ]);
        } elseif ($contractType === 'commission_based') {
            $response->assertJsonStructure([
                'data' => [
                    'cadete_info' => ['id', 'name', 'contract_type', 'contract_type_label', 'commission_percentage'],
                    'calculated_amounts' => ['commission_amount', 'gross_total', 'net_amount']
                ]
            ]);
        }
        
        // Verificar información del cadete
        $this->assertEquals($this->cadete->id, $data['cadete_info']['id']);
        $this->assertEquals($contractType, $data['cadete_info']['contract_type']);
        
        // Verificar campos según el tipo de contratación
        if ($contractType === 'fixed_salary') {
            $this->assertEquals(50000, $data['cadete_info']['base_salary']);
            $this->assertEquals(50000, $data['calculated_amounts']['base_salary']);
            $this->assertEquals(975.00, $data['calculated_amounts']['commission_amount']);
        } elseif ($contractType === 'commission_based') {
            $this->assertEquals(15, $data['cadete_info']['commission_percentage']);
            $this->assertEquals(975.00, $data['calculated_amounts']['commission_amount']);
        }

        // Verificar información del período
        $this->assertEquals($testDate, $data['period_info']['period_start']);
        $this->assertEquals($testDate, $data['period_info']['period_end']);
        $this->assertEquals(1, $data['period_info']['days_count']);

        // Verificar resumen de comisiones
        $this->assertEquals(8, $data['commissions_summary']['total_commissions']);
        $this->assertEquals(5, $data['commissions_summary']['delivered_commissions']);
        $this->assertEquals(3, $data['commissions_summary']['pending_commissions']);
        $this->assertEquals(6500.00, $data['commissions_summary']['total_commission_value']);
        $this->assertEquals(975.00, $data['commissions_summary']['commission_amount']); // 6500 * 0.15

        // Verificar montos calculados según el tipo de contratación
        if ($contractType === 'fixed_salary') {
            $this->assertEquals(50975.00, $data['calculated_amounts']['gross_total']); // 50000 + 975
        } elseif ($contractType === 'commission_based') {
            $this->assertEquals(975.00, $data['calculated_amounts']['gross_total']); // Solo comisión
        }
        
        $this->assertEquals(0, $data['calculated_amounts']['deductions']);
        $this->assertEquals($data['calculated_amounts']['gross_total'], $data['calculated_amounts']['net_amount']);
    }

    /** @test */
    public function admin_can_calculate_cadete_payment_with_default_values()
    {
        // Crear comisiones para el cadete
        Commission::factory()->count(3)->create([
            'cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 2000.00,
            'date' => now()->format('Y-m-d')
        ]);

        $requestData = [
            'cadete_id' => $this->cadete->id,
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d')
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $contractType = $data['cadete_info']['contract_type'];
        
        // Verificar estructura base
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'cadete_info' => ['id', 'name', 'contract_type', 'contract_type_label'],
                'period_info' => ['period_start', 'period_end', 'days_count'],
                'commissions_summary' => [
                    'total_commissions', 'delivered_commissions', 'pending_commissions',
                    'total_commission_value', 'commission_amount'
                ],
                'calculated_amounts' => [
                    'gross_total', 'net_amount'
                ]
            ]
        ]);
        
        // Verificar campos específicos según el tipo de contratación
        if ($contractType === 'fixed_salary') {
            $response->assertJsonStructure([
                'data' => [
                    'cadete_info' => ['id', 'name', 'contract_type', 'contract_type_label', 'base_salary'],
                    'calculated_amounts' => ['base_salary', 'commission_amount', 'gross_total', 'net_amount']
                ]
            ]);
            $this->assertEquals($this->cadete->base_salary ?? 0, $data['cadete_info']['base_salary']);
        } elseif ($contractType === 'commission_based') {
            $response->assertJsonStructure([
                'data' => [
                    'cadete_info' => ['id', 'name', 'contract_type', 'contract_type_label', 'commission_percentage'],
                    'calculated_amounts' => ['commission_amount', 'gross_total', 'net_amount']
                ]
            ]);
            $this->assertEquals($this->cadete->commission_percentage ?? 0, $data['cadete_info']['commission_percentage']);
        }

        // Verificar cálculo de comisión
        $expectedCommissionAmount = 6000.00 * (($this->cadete->commission_percentage ?? 0) / 100);
        $this->assertEquals($expectedCommissionAmount, $data['commissions_summary']['commission_amount']);
    }

    /** @test */
    public function calculate_payment_validates_cadete_id_exists()
    {
        $requestData = [
            'cadete_id' => 999, // ID inexistente
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d')
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cadete_id']);
    }

    /** @test */
    public function calculate_payment_validates_period_dates()
    {
        $requestData = [
            'cadete_id' => $this->cadete->id,
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->subDay()->format('Y-m-d') // Fecha fin antes que inicio
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['period_end']);
    }

    /** @test */
    public function calculate_payment_validates_percentage_ranges()
    {
        $requestData = [
            'cadete_id' => $this->cadete->id,
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d'),
            'commission_percentage' => 150, // Porcentaje inválido (> 100)
            'income_percentage' => -10 // Porcentaje negativo
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['commission_percentage', 'income_percentage']);
    }

    /** @test */
    public function calculate_payment_rejects_non_cadete_user()
    {
        // Crear un usuario que no sea cadete
        $nonCadeteUser = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
            'branch_id' => $this->branch->id
        ]);

        $requestData = [
            'cadete_id' => $nonCadeteUser->id,
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d')
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'El usuario especificado no es un cadete'
        ]);
    }

    /** @test */
    public function calculate_payment_handles_cadete_externo()
    {
        // Crear un cadete externo con contract_type commission_based
        $cadeteExterno = User::factory()->create([
            'role' => UserRole::CADETE_EXTERNO->value,
            'branch_id' => $this->branch->id,
            'commission_percentage' => 20.0,
            'contract_type' => 'commission_based'
        ]);

        // Crear comisiones para el cadete externo
        Commission::factory()->count(2)->create([
            'cadete_id' => $cadeteExterno->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 1500.00,
            'date' => now()->format('Y-m-d')
        ]);

        $requestData = [
            'cadete_id' => $cadeteExterno->id,
            'period_start' => now()->format('Y-m-d'),
            'period_end' => now()->format('Y-m-d')
        ];

        $response = $this->postJson('/api/admin/cadete-payments/calculate', $requestData);

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals($cadeteExterno->id, $data['cadete_info']['id']);
        $this->assertEquals('commission_based', $data['cadete_info']['contract_type']);
        $this->assertEquals(20.0, $data['cadete_info']['commission_percentage']);
        
        // Verificar cálculo de comisión
        $expectedCommissionAmount = 3000.00 * (20.0 / 100);
        $this->assertEquals($expectedCommissionAmount, $data['commissions_summary']['commission_amount']);
    }
}
