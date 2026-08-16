<?php

namespace Tests\Feature;

use App\Services\IvaCalculationService;
use App\Shared\Enums\IvaStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-491 (IVA AUTOMATICO).
 *
 * El servicio de cálculo siempre estuvo bien, pero el alta/edición de clientes
 * descartaba iva_status: el DTO no tenía el campo y el repositorio nunca lo
 * escribía. Resultado en producción: los 3418 clientes quedaron en "auto" y
 * ninguna de las 9352 comisiones aplicó IVA jamás.
 *
 * Estos tests cubren el camino que nadie cubría: que el valor elegido en el
 * formulario sobreviva al POST/PUT, y que "always"/"exempt" no dependan de que
 * haya un método de pago elegido.
 */
class IvaStatusPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_create_customer_persists_iva_status(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/customers', [
            'type' => 'individual',
            'dni' => 30111222,
            'name' => 'Cliente',
            'last_name' => 'Con IVA',
            'email' => 'iva.siempre@example.com',
            'iva_status' => IvaStatus::ALWAYS->value,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customers', [
            'email' => 'iva.siempre@example.com',
            'iva_status' => IvaStatus::ALWAYS->value,
        ]);
    }

    public function test_create_customer_defaults_to_auto_when_not_sent(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/customers', [
            'type' => 'individual',
            'dni' => 30111223,
            'name' => 'Cliente',
            'last_name' => 'Sin Iva Status',
            'email' => 'iva.default@example.com',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('customers', [
            'email' => 'iva.default@example.com',
            'iva_status' => IvaStatus::AUTO->value,
        ]);
    }

    public function test_create_customer_rejects_invalid_iva_status(): void
    {
        $this->actingAs($this->admin)->postJson('/api/customers', [
            'type' => 'individual',
            'dni' => 30111224,
            'name' => 'Cliente',
            'email' => 'iva.invalido@example.com',
            'iva_status' => 'a_veces',
        ])->assertStatus(422);
    }

    public function test_update_customer_persists_iva_status(): void
    {
        $customer = Customer::factory()->create(['iva_status' => IvaStatus::AUTO->value]);

        $this->actingAs($this->admin)->putJson("/api/customers/{$customer->id}", [
            'type' => 'individual',
            'name' => $customer->name,
            'last_name' => $customer->last_name ?? '',
            'email' => $customer->email,
            'iva_status' => IvaStatus::EXEMPT->value,
        ])->assertOk();

        $this->assertSame(IvaStatus::EXEMPT, $customer->fresh()->iva_status);
    }

    public function test_update_customer_preserves_iva_status_when_not_sent(): void
    {
        $customer = Customer::factory()->create(['iva_status' => IvaStatus::ALWAYS->value]);

        $this->actingAs($this->admin)->putJson("/api/customers/{$customer->id}", [
            'type' => 'individual',
            'name' => 'Nombre Editado',
            'last_name' => $customer->last_name ?? '',
            'email' => $customer->email,
        ])->assertOk();

        $this->assertSame(IvaStatus::ALWAYS, $customer->fresh()->iva_status);
    }

    // --- "always" / "exempt" no dependen del método de pago ---

    public function test_always_applies_without_payment_method(): void
    {
        $service = new IvaCalculationService();
        $customer = Customer::factory()->create(['iva_status' => IvaStatus::ALWAYS->value]);

        $result = $service->calculateIva($customer, null, 1000);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(210.00, $result['iva_amount']);
        $this->assertEquals(1210.00, $result['total_with_iva']);
    }

    public function test_exempt_does_not_apply_without_payment_method(): void
    {
        $service = new IvaCalculationService();
        $customer = Customer::factory()->create(['iva_status' => IvaStatus::EXEMPT->value]);

        $result = $service->calculateIva($customer, null, 1000);

        $this->assertFalse($result['iva_applied']);
        $this->assertEquals(1000.00, $result['total_with_iva']);
    }

    public function test_auto_does_not_apply_without_payment_method(): void
    {
        $service = new IvaCalculationService();
        $customer = Customer::factory()->create([
            'iva_status' => IvaStatus::AUTO->value,
            'auto_calculate_iva' => true,
        ]);

        $result = $service->calculateIva($customer, null, 1000);

        $this->assertFalse($result['iva_applied']);
        $this->assertEquals(1000.00, $result['total_with_iva']);
    }
}
