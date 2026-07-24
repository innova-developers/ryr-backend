<?php

namespace Tests\Feature;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionCadeteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cadete;
    protected User $cadeteExterno;
    protected Commission $commission;
    protected Customer $customer;
    protected Branch $branch;
    protected Destination $destination;
    protected Location $originLocation;
    protected Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear admin
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'email' => 'admin@test.com',
        ]);

        // Crear cadete
        $this->cadete = User::factory()->create([
            'role' => 'cadete',
            'email' => 'cadete@test.com',
        ]);

        // Crear cadete externo
        $this->cadeteExterno = User::factory()->create([
            'role' => 'cadete_externo',
            'email' => 'cadeteexterno@test.com',
        ]);

        // Crear customer
        $this->customer = Customer::factory()->create([
            'email' => 'customer@test.com',
        ]);

        // Crear branch
        $this->branch = Branch::factory()->create();

        // Crear destination
        $this->destination = Destination::factory()->create();

        // Crear locations
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();

        // Crear commission
        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA,
            'total' => 5000.00,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_assign_cadete_to_commission(): void
    {
        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $this->cadete->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete asignado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => $this->cadete->id,
                    // Asignar cadete no cambia el estado (decoplado del workflow).
                    'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
        ]);
    }

    public function test_admin_can_assign_cadete_externo_to_commission(): void
    {
        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $this->cadeteExterno->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete asignado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => $this->cadeteExterno->id,
                    'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
                ],
            ]);
    }

    public function test_cannot_assign_non_cadete_user(): void
    {
        $nonCadeteUser = User::factory()->create(['role' => 'cliente']);

        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $nonCadeteUser->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['cadete_id']);
    }

    public function test_can_assign_cadete_to_commission_in_any_status(): void
    {
        // Cambiar comisión a un estado que antes no permitía asignación
        $this->commission->update(['status' => CommissionStatus::ENTREGADO]);

        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $this->cadete->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete asignado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => $this->cadete->id,
                    // Asignar no cambia el estado: queda en ENTREGADO.
                    'status' => CommissionStatus::ENTREGADO->value,
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::ENTREGADO->value,
        ]);
    }

    public function test_cannot_assign_same_cadete_twice(): void
    {
        // Primera asignación
        $this->commission->update(['cadete_id' => $this->cadete->id]);

        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $this->cadete->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Este cadete ya está asignado a la comisión',
            ]);
    }

    public function test_admin_can_unassign_cadete_from_commission(): void
    {
        // Primero asignar un cadete
        $this->commission->update(['cadete_id' => $this->cadete->id]);

        $response = $this->deleteJson("/api/commissions/{$this->commission->id}/unassign-cadete");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete desasignado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => null,
                    'status' => CommissionStatus::BUSCANDO_CADETE->value,
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'cadete_id' => null,
            'status' => CommissionStatus::BUSCANDO_CADETE->value,
        ]);
    }

    public function test_cannot_unassign_cadete_from_commission_without_cadete(): void
    {
        $response = $this->deleteJson("/api/commissions/{$this->commission->id}/unassign-cadete");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Esta comisión no tiene un cadete asignado',
            ]);
    }

    public function test_can_unassign_cadete_from_commission_in_any_status(): void
    {
        // Asignar un cadete y cambiar a un estado que antes no permitía desasignación
        $this->commission->update([
            'cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::ENTREGADO,
        ]);

        $response = $this->deleteJson("/api/commissions/{$this->commission->id}/unassign-cadete");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete desasignado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => null,
                    // Desde ENTREGADO la desasignación NO fuerza BUSCANDO_CADETE: mantiene estado.
                    'status' => CommissionStatus::ENTREGADO->value,
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'cadete_id' => null,
            'status' => CommissionStatus::ENTREGADO->value,
        ]);
    }

    public function test_admin_can_change_cadete_assignment(): void
    {
        // Primero asignar un cadete
        $this->commission->update(['cadete_id' => $this->cadete->id]);

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/change-cadete", [
            'cadete_id' => $this->cadeteExterno->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete cambiado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => $this->cadeteExterno->id,
                    // Cambiar cadete no cambia el estado (queda SOLICITUD_RECIBIDA).
                    'status' => CommissionStatus::SOLICITUD_RECIBIDA->value,
                ],
                'previous_cadete_id' => $this->cadete->id,
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'cadete_id' => $this->cadeteExterno->id,
        ]);
    }

    public function test_cannot_change_to_same_cadete(): void
    {
        // Primero asignar un cadete
        $this->commission->update(['cadete_id' => $this->cadete->id]);

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/change-cadete", [
            'cadete_id' => $this->cadete->id,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'La comisión ya tiene asignado este cadete',
            ]);
    }

    public function test_can_change_cadete_assignment_in_any_status(): void
    {
        // Asignar un cadete y cambiar a un estado que antes no permitía cambio
        $this->commission->update([
            'cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::ENTREGADO,
        ]);

        $response = $this->patchJson("/api/commissions/{$this->commission->id}/change-cadete", [
            'cadete_id' => $this->cadeteExterno->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Cadete cambiado exitosamente',
                'commission' => [
                    'id' => $this->commission->id,
                    'cadete_id' => $this->cadeteExterno->id,
                    // Cambiar cadete no cambia el estado: queda ENTREGADO.
                    'status' => CommissionStatus::ENTREGADO->value,
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'cadete_id' => $this->cadeteExterno->id,
            'status' => CommissionStatus::ENTREGADO->value,
        ]);
    }

    public function test_can_get_assigned_cadete(): void
    {
        // Primero asignar un cadete
        $this->commission->update(['cadete_id' => $this->cadete->id]);

        $response = $this->getJson("/api/commissions/{$this->commission->id}/assigned-cadete");

        $response->assertStatus(200)
            ->assertJson([
                'cadete' => [
                    'id' => $this->cadete->id,
                    'email' => $this->cadete->email,
                ],
            ]);
    }

    public function test_get_assigned_cadete_returns_null_when_no_cadete(): void
    {
        $response = $this->getJson("/api/commissions/{$this->commission->id}/assigned-cadete");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Esta comisión no tiene un cadete asignado',
                'cadete' => null,
            ]);
    }

    public function test_can_get_commissions_by_cadete(): void
    {
        // Comisiones LEVANTADAS por el cadete (vinculación = pickup_cadete_id)
        $commission1 = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::CADETE_ASIGNADO,
        ]);

        $commission2 = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'destination_id' => $this->destination->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'cadete_id' => $this->cadete->id,
            'pickup_cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::EN_PROCESO_ENTREGA,
        ]);

        $response = $this->getJson("/api/cadetes/{$this->cadete->id}/commissions");

        $response->assertStatus(200)
            ->assertJson([
                'cadete' => [
                    'id' => $this->cadete->id,
                    'email' => $this->cadete->email,
                ],
                'total' => 2,
            ])
            ->assertJsonCount(2, 'commissions');
    }

    public function test_cannot_get_commissions_by_non_cadete_user(): void
    {
        $nonCadeteUser = User::factory()->create(['role' => 'cliente']);

        $response = $this->getJson("/api/cadetes/{$nonCadeteUser->id}/commissions");

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'El usuario especificado no es un cadete',
            ]);
    }

    public function test_requires_authentication(): void
    {
        // No autenticar al usuario
        $this->app['auth']->forgetGuards();

        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $this->cadete->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_requires_admin_role(): void
    {
        Sanctum::actingAs($this->cadete);

        $response = $this->postJson("/api/commissions/{$this->commission->id}/assign-cadete", [
            'cadete_id' => $this->cadeteExterno->id,
        ]);

        $response->assertStatus(403);
    }
}
