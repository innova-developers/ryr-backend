<?php

namespace Tests\Feature\Admin;

use App\Shared\Enums\PaymentMethod;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cadete;
    private \App\Shared\Models\Customer $customer;
    private \App\Shared\Models\Destination $destination;
    private \App\Shared\Models\Branch $branch;
    private \App\Shared\Models\Location $originLocation;
    private \App\Shared\Models\Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
        ]);

        $this->cadete = User::factory()->create([
            'role' => UserRole::CADETE,
        ]);

        // Crear registros dependientes necesarios para las comisiones
        $this->customer = \App\Shared\Models\Customer::factory()->create();
        $this->destination = \App\Shared\Models\Destination::factory()->create();
        $this->branch = \App\Shared\Models\Branch::factory()->create();
        $this->originLocation = \App\Shared\Models\Location::factory()->create();
        $this->destinationLocation = \App\Shared\Models\Location::factory()->create();
    }

    public function test_admin_can_get_payment_methods()
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['value', 'label'],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(4, $data);

        $expectedMethods = [
            'EFECTIVO' => 'Efectivo',
            'CHEQUE' => 'Cheque',
            'TRANSFERENCIA' => 'Transferencia',
            'CUENTA_CORRIENTE' => 'Cuenta Corriente',
        ];

        foreach ($expectedMethods as $value => $label) {
            $this->assertContains([
                'value' => $value,
                'label' => $label,
            ], $data);
        }
    }

    public function test_admin_can_associate_payment_method_with_commission()
    {
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/payment-methods/associate', [
                'commission_id' => $commission->id,
                'payment_method' => PaymentMethod::EFECTIVO->value,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method associated successfully',
                'data' => [
                    'commission_id' => $commission->id,
                    'payment_method' => PaymentMethod::EFECTIVO->value,
                    'payment_method_label' => 'Efectivo',
                ],
            ]);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'payment_method' => PaymentMethod::EFECTIVO->value,
        ]);
    }

    public function test_admin_cannot_associate_payment_method_with_invalid_commission()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/payment-methods/associate', [
                'commission_id' => 99999,
                'payment_method' => PaymentMethod::EFECTIVO->value,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commission_id']);
    }

    public function test_admin_cannot_associate_invalid_payment_method()
    {
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/payment-methods/associate', [
                'commission_id' => $commission->id,
                'payment_method' => 'INVALID_METHOD',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_admin_can_get_payment_methods_summary()
    {
        // Crear comisiones con diferentes métodos de pago
        Commission::factory()->count(3)->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => PaymentMethod::EFECTIVO,
        ]);

        Commission::factory()->count(2)->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => PaymentMethod::TRANSFERENCIA,
        ]);

        Commission::factory()->count(1)->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/payment-methods/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'total_with_method',
                    'total_without_method',
                    'total_commissions',
                ],
            ]);

        $data = $response->json('data');

        $this->assertEquals(5, $data['total_with_method']);
        $this->assertEquals(1, $data['total_without_method']);
        $this->assertEquals(6, $data['total_commissions']);

        $this->assertCount(2, $data['summary']);

        // Verificar que el resumen incluya los métodos correctos
        $efectivoSummary = collect($data['summary'])->firstWhere('payment_method', PaymentMethod::EFECTIVO->value);
        $transferenciaSummary = collect($data['summary'])->firstWhere('payment_method', PaymentMethod::TRANSFERENCIA->value);

        $this->assertEquals(3, $efectivoSummary['count']);
        $this->assertEquals(2, $transferenciaSummary['count']);
    }

    public function test_non_admin_cannot_access_payment_methods_endpoints()
    {
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
        ]);

        // Intentar obtener métodos de pago
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/admin/payment-methods');
        $response->assertStatus(403);

        // Intentar asociar método de pago
        $response = $this->actingAs($this->cadete)
            ->postJson('/api/admin/payment-methods/associate', [
                'commission_id' => $commission->id,
                'payment_method' => PaymentMethod::EFECTIVO->value,
            ]);
        $response->assertStatus(403);

        // Intentar obtener resumen
        $response = $this->actingAs($this->cadete)
            ->getJson('/api/admin/payment-methods/summary');
        $response->assertStatus(403);
    }

    public function test_associate_payment_method_validation_requires_commission_id()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/payment-methods/associate', [
                'payment_method' => PaymentMethod::EFECTIVO->value,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commission_id']);
    }

    public function test_associate_payment_method_validation_requires_payment_method()
    {
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->admin->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/payment-methods/associate', [
                'commission_id' => $commission->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }
}
