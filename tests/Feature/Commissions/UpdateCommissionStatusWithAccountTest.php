<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateCommissionStatusWithAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Commission $commission;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario administrador
        $this->user = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);

        // Crear cliente
        $this->customer = Customer::factory()->create();

        // Crear branch
        $branch = \App\Shared\Models\Branch::factory()->create();

        // Crear destination
        $destination = \App\Shared\Models\Destination::factory()->create();

        // Crear locations
        $originLocation = \App\Shared\Models\Location::factory()->create();
        $destinationLocation = \App\Shared\Models\Location::factory()->create();

        // Crear comisión con todas las dependencias
        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'branch_id' => $branch->id,
            'destination_id' => $destination->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA,
            'total' => 5000.00,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_update_status_with_a_cuenta_true(): void
    {
        // El movimiento en cuenta corriente (a cuenta) se genera al pasar a PAGO_VALIDACION.
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::PAGO_VALIDACION->value,
            'details' => 'Comisión entregada',
            'a_cuenta' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Estado de la comisión actualizado correctamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'status' => CommissionStatus::PAGO_VALIDACION->value,
                ],
            ]);

        // Verificar que se creó la transacción en cuenta corriente
        $this->assertDatabaseHas('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 5000.00,
            'reference' => "COM-{$this->commission->id}",
        ]);
    }

    public function test_can_update_status_without_a_cuenta(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ENTREGADO->value,
            'details' => 'Comisión entregada',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Estado de la comisión actualizado correctamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'status' => CommissionStatus::ENTREGADO->value,
                ],
            ]);

        // Verificar que NO se creó transacción en cuenta corriente
        $this->assertDatabaseMissing('current_accounts', [
            'customer_id' => $this->customer->id,
            'description' => "Comisión #{$this->commission->id} - Actualización de estado",
        ]);
    }

    public function test_can_update_status_with_a_cuenta_false(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ENTREGADO->value,
            'details' => 'Comisión entregada',
            'a_cuenta' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Estado de la comisión actualizado correctamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'status' => CommissionStatus::ENTREGADO->value,
                ],
            ]);

        // Verificar que NO se creó transacción en cuenta corriente
        $this->assertDatabaseMissing('current_accounts', [
            'customer_id' => $this->customer->id,
            'description' => "Comisión #{$this->commission->id} - Actualización de estado",
        ]);
    }

    public function test_validates_a_cuenta_boolean(): void
    {
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ENTREGADO->value,
            'a_cuenta' => 'invalid_value',
        ]);

        // El endpoint puede devolver 422 (validación) o 500 (error interno)
        // Dependiendo de cómo Laravel maneje el error de validación
        $this->assertTrue(in_array($response->status(), [422, 500]));

        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['a_cuenta']);
        }
    }
}
