<?php

namespace Tests\Feature;

use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentMethodControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Commission $commission;
    private Branch $branch;
    private Destination $destination;
    private Location $originLocation;
    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->branch = Branch::factory()->create(['name' => 'Sucursal Test']);
        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'administrador',
        ]);
        $this->customer = Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'auto_calculate_iva' => true,
        ]);
        $this->destination = Destination::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
        
        $this->commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => PaymentMethod::EFECTIVO,
            'total' => 1000.00,
            'iva_amount' => 0.00,
            'iva_applied' => false,
        ]);
        
        Sanctum::actingAs($this->user);
    }

    public function test_can_get_payment_methods(): void
    {
        $response = $this->getJson('/api/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'value',
                        'label',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(count(PaymentMethod::cases()), $data);
    }

    public function test_can_associate_payment_method(): void
    {
        $response = $this->postJson('/api/payment-methods/associate', [
            'commission_id' => $this->commission->id,
            'payment_method' => 'CHEQUE',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method associated successfully',
                'data' => [
                    'commission_id' => $this->commission->id,
                    'payment_method' => 'CHEQUE',
                    'payment_method_label' => 'Cheque',
                    'iva_applied' => false,
                    'iva_amount' => '0.00',
                    'total_with_iva' => '1000.00',
                ]
            ]);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'payment_method' => 'CHEQUE',
        ]);
    }

    public function test_can_change_payment_method_to_transfer_with_iva(): void
    {
        $response = $this->postJson('/api/payment-methods/associate', [
            'commission_id' => $this->commission->id,
            'payment_method' => 'TRANSFERENCIA',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method associated successfully',
            ]);

        // Verificar que se aplicó IVA
        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'payment_method' => 'TRANSFERENCIA',
            'iva_applied' => true,
            'iva_amount' => 210.00, // 21% de 1000
            'total' => 1210.00, // 1000 + 210
        ]);
    }

    public function test_can_change_payment_method_from_transfer_to_cash_removes_iva(): void
    {
        // Primero cambiar a TRANSFERENCIA para aplicar IVA
        $this->commission->update([
            'payment_method' => PaymentMethod::TRANSFERENCIA,
            'iva_applied' => true,
            'iva_amount' => 210.00,
            'total' => 1210.00,
        ]);

        $response = $this->postJson('/api/payment-methods/associate', [
            'commission_id' => $this->commission->id,
            'payment_method' => 'EFECTIVO',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Payment method associated successfully',
            ]);

        // Verificar que se removió IVA
        $this->assertDatabaseHas('commissions', [
            'id' => $this->commission->id,
            'payment_method' => 'EFECTIVO',
            'iva_applied' => false,
            'iva_amount' => 0.00,
            'total' => 1000.00,
        ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/payment-methods/associate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commission_id', 'payment_method']);
    }

    public function test_validates_commission_exists(): void
    {
        $response = $this->postJson('/api/payment-methods/associate', [
            'commission_id' => 99999,
            'payment_method' => 'CHEQUE',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['commission_id']);
    }

    public function test_validates_payment_method_is_valid(): void
    {
        $response = $this->postJson('/api/payment-methods/associate', [
            'commission_id' => $this->commission->id,
            'payment_method' => 'INVALID_METHOD',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_returns_404_for_nonexistent_commission(): void
    {
        $response = $this->postJson('/api/payment-methods/associate', [
            'commission_id' => 99999,
            'payment_method' => 'CHEQUE',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_get_payment_methods_summary(): void
    {
        // Crear algunas comisiones con diferentes métodos de pago
        Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => PaymentMethod::EFECTIVO,
        ]);

        Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'user_id' => $this->user->id,
            'branch_id' => $this->branch->id,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'payment_method' => PaymentMethod::TRANSFERENCIA,
        ]);

        $response = $this->getJson('/api/payment-methods/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'total_with_method',
                    'total_without_method',
                    'total_commissions',
                ]
            ]);
    }
}
