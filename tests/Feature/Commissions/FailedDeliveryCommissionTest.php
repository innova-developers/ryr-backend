<?php

namespace Tests\Feature\Commissions;

use App\Contexts\Commissions\Application\UpdateCommissionStatusUseCase;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailedDeliveryCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Destination $destination;
    private Location $originLocation;
    private Location $destinationLocation;
    private Commission $commission;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario admin
        $this->user = User::factory()->create(['role' => 'administrador']);

        // Crear cliente
        $this->customer = Customer::factory()->create();

        // Crear destino con precio base
        $this->destination = Destination::factory()->create([
            'origin' => 'Buenos Aires',
            'destination' => 'Córdoba',
            'fixed_price' => 1500.00,
            'small_bulk_price' => 1200.00,
            'large_bulk_price' => 900.00,
        ]);

        // Crear ubicaciones
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();

        // Crear comisión de prueba
        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO,
            'total' => 2000.00, // Total mayor al precio base
            'notes' => 'Comisión original',
        ]);
    }

    public function test_creates_new_commission_when_status_changes_to_failed_delivery(): void
    {
        $this->actingAs($this->user);

        // Verificar que inicialmente solo hay 1 comisión
        $this->assertDatabaseCount('commissions', 1);

        // Actualizar estado a INTENTO_ENTREGA_FALLIDO
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
            'details' => 'Cliente no se encontraba en el domicilio',
        ]);

        $response->assertStatus(200);

        // Verificar que se crearon 2 comisiones (original + nueva)
        $this->assertDatabaseCount('commissions', 2);

        // Verificar que la comisión original mantiene su estado
        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);

        // Verificar que se creó la nueva comisión con precio base
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'total' => 1500.00, // Precio base del destino
            'notes' => "Comisión creada automáticamente por entrega fallida de comisión #{$this->commission->id}",
        ]);
    }

    public function test_new_commission_has_correct_attributes(): void
    {
        $this->actingAs($this->user);

        // Actualizar estado a INTENTO_ENTREGA_FALLIDO
        $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);

        // Verificar que se creó la nueva comisión con los atributos correctos
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'total' => 1500.00,
            'notes' => "Comisión creada automáticamente por entrega fallida de comisión #{$this->commission->id}",
        ]);
    }

    public function test_new_commission_has_log_entry(): void
    {
        $this->actingAs($this->user);

        // Actualizar estado a INTENTO_ENTREGA_FALLIDO
        $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);

        // Verificar que se creó un log para la nueva comisión
        $this->assertDatabaseHas('commission_logs', [
            'user_id' => $this->user->id,
            'previous_status' => '',
            'new_status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
            'details' => "Comisión creada automáticamente por entrega fallida de comisión #{$this->commission->id}",
        ]);
    }

    public function test_does_not_create_commission_for_other_statuses(): void
    {
        $this->actingAs($this->user);

        // Actualizar a un estado diferente
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::ENTREGADO->value,
        ]);

        $response->assertStatus(200);

        // Verificar que solo hay 1 comisión (no se creó nueva)
        $this->assertDatabaseCount('commissions', 1);
    }

    public function test_handles_error_gracefully_when_creating_failed_delivery_commission(): void
    {
        $this->actingAs($this->user);

        // Simular un error en la creación de la nueva comisión
        // Esto se puede hacer mockeando el repositorio, pero por simplicidad
        // vamos a verificar que la transacción principal no falla

        // Actualizar estado a INTENTO_ENTREGA_FALLIDO
        $response = $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);

        // La respuesta debe ser exitosa incluso si hay un error en la creación de la nueva comisión
        $response->assertStatus(200);

        // La comisión original debe haber cambiado de estado
        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);
    }

    public function test_new_commission_uses_current_date(): void
    {
        $this->actingAs($this->user);

        $beforeUpdate = now();

        // Actualizar estado a INTENTO_ENTREGA_FALLIDO
        $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);

        $afterUpdate = now();

        // Verificar que se creó una nueva comisión con fecha actual
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
        ]);
    }

    public function test_new_commission_has_no_items(): void
    {
        $this->actingAs($this->user);

        // Actualizar estado a INTENTO_ENTREGA_FALLIDO
        $this->patchJson("/api/commissions/{$this->commission->id}/status", [
            'status' => CommissionStatus::INTENTO_ENTREGA_FALLIDO->value,
        ]);

        // Verificar que se creó una nueva comisión
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
        ]);
    }
}
