<?php

namespace Tests\Feature\Commissions;

use App\Mail\CommissionStatusChangedMail;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Customer $customer;
    private Commission $commission;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar Mail fake desde el inicio
        Mail::fake();

        // Crear usuario administrador
        $this->adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        Sanctum::actingAs($this->adminUser);

        // Crear sucursal
        $branch = Branch::factory()->create();

        // Crear cliente con email y teléfono
        $this->customer = Customer::factory()->create([
            'email' => 'cliente@example.com',
            'mobile' => '2915123456',
            'branch_id' => $branch->id,
        ]);

        // Crear ubicaciones
        $originLocation = Location::factory()->create();
        $destinationLocation = Location::factory()->create();

        // Crear destino
        $destination = \App\Shared\Models\Destination::factory()->create();

        // Crear comisión
        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $this->adminUser->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::PENDIENTE,
        ]);
    }

    public function test_sends_notifications_when_status_changes_to_aceptado(): void
    {
        // Debug: verificar que el customer tiene email
        $this->assertNotEmpty($this->customer->email, 'Customer should have email');
        $this->assertEquals('cliente@example.com', $this->customer->email, 'Customer email should match');

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ACEPTADO->value,
            'details' => 'Presupuesto aceptado por el cliente',
        ]);

        $response->assertStatus(200);

        // Verificar que se envió el email - simplificar la verificación
        Mail::assertSent(CommissionStatusChangedMail::class);
    }

    public function test_sends_notifications_when_status_changes_to_retirado(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::RETIRADO->value,
            'details' => 'Envío retirado para transporte',
        ]);

        $response->assertStatus(200);

        // Verificar que se envió el email
        Mail::assertSent(CommissionStatusChangedMail::class);
    }

    public function test_sends_notifications_when_status_changes_to_entregado(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ENTREGADO->value,
            'details' => 'Envío entregado exitosamente',
        ]);

        $response->assertStatus(200);

        // Verificar que se envió el email
        Mail::assertSent(CommissionStatusChangedMail::class);
    }

    public function test_sends_notifications_when_status_changes_to_cancelado(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::CANCELADO->value,
            'details' => 'Comisión cancelada por solicitud del cliente',
        ]);

        $response->assertStatus(200);

        // Verificar que se envió el email
        Mail::assertSent(CommissionStatusChangedMail::class);
    }

    public function test_sends_notifications_when_status_changes_to_pagado(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::PAGADO->value,
            'details' => 'Pago confirmado',
        ]);

        $response->assertStatus(200);

        // Verificar que se envió el email
        Mail::assertSent(CommissionStatusChangedMail::class);
    }

    public function test_does_not_send_notifications_for_unimportant_statuses(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::DEPOSITO->value,
            'details' => 'En depósito',
        ]);

        $response->assertStatus(200);

        // Verificar que NO se envió el email
        Mail::assertNotSent(CommissionStatusChangedMail::class);
    }

    public function test_does_not_send_notifications_when_customer_has_no_email(): void
    {
        // Crear un nuevo cliente con email vacío (que será tratado como null en el servicio)
        $customerWithoutEmail = Customer::factory()->create([
            'email' => '',
            'mobile' => '2915123456',
            'branch_id' => $this->customer->branch_id,
        ]);

        // Crear una nueva comisión para este cliente
        $commission = Commission::factory()->create([
            'client_id' => $customerWithoutEmail->id,
            'destination_id' => $this->commission->destination_id,
            'branch_id' => $this->customer->branch_id,
            'user_id' => $this->adminUser->id,
            'origin_location_id' => $this->commission->origin_location_id,
            'destination_location_id' => $this->commission->destination_location_id,
            'status' => CommissionStatus::PENDIENTE,
        ]);

        $response = $this->patchJson("/api/commissions/{$commission->id}/status", [
            'status' => CommissionStatus::ACEPTADO->value,
        ]);

        $response->assertStatus(200);

        // Verificar que NO se envió el email
        Mail::assertNotSent(CommissionStatusChangedMail::class);
    }

    public function test_whatsapp_service_is_called_when_customer_has_phone(): void
    {
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendCommissionStatusNotification')
                ->once()
                ->with(
                    '2915123456',
                    $this->commission->id,
                    CommissionStatus::ACEPTADO->value,
                    $this->customer->full_name,
                    null,
                    \Mockery::type('object')
                )
                ->andReturn(true);
        });

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ACEPTADO->value,
        ]);

        $response->assertStatus(200);
    }

    public function test_whatsapp_service_is_not_called_when_customer_has_no_phone(): void
    {
        // Actualizar cliente sin teléfono
        $this->customer->update(['mobile' => null, 'phone' => null]);

        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldNotReceive('sendCommissionStatusNotification');
        });

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ACEPTADO->value,
        ]);

        $response->assertStatus(200);
    }

    public function test_notification_service_handles_errors_gracefully(): void
    {
        // Simular error en el servicio de WhatsApp
        $this->mock(WhatsAppService::class, function ($mock) {
            $mock->shouldReceive('sendCommissionStatusNotification')
                ->andThrow(new \Exception('WhatsApp service error'));
        });

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ACEPTADO->value,
        ]);

        $response->assertStatus(200);

        // Verificar que el email sí se envió a pesar del error de WhatsApp
        Mail::assertSent(CommissionStatusChangedMail::class);
    }
}
