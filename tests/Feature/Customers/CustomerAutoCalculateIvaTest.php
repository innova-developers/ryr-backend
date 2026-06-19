<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAutoCalculateIvaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Branch $branch;

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

        Sanctum::actingAs($this->user);
    }

    public function test_customer_response_includes_auto_calculate_iva_field(): void
    {
        $response = $this->getJson("/api/customers/{$this->customer->id}");

        $response->assertStatus(200);

        // Debug: ver qué está devolviendo la respuesta
        $responseData = $response->json();
        $this->assertArrayHasKey('auto_calculate_iva', $responseData);
        $this->assertTrue($responseData['auto_calculate_iva']);
    }

    public function test_customer_list_includes_auto_calculate_iva_field(): void
    {
        // Crear otro cliente con auto_calculate_iva = false
        Customer::factory()->create([
            'branch_id' => $this->branch->id,
            'auto_calculate_iva' => false,
        ]);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'dni',
                        'name',
                        'last_name',
                        'email',
                        'address',
                        'city',
                        'phone',
                        'is_premium',
                        'auto_calculate_iva',
                        'user',
                        'branch',
                        'balance',
                        'created_at',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Verificar que ambas comisiones incluyen el campo auto_calculate_iva
        foreach ($data as $customer) {
            $this->assertArrayHasKey('auto_calculate_iva', $customer);
        }
    }

    public function test_can_update_auto_calculate_iva_field(): void
    {
        $response = $this->patchJson("/api/customers/{$this->customer->id}/auto-calculate-iva", [
            'auto_calculate_iva' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Configuración de IVA actualizada exitosamente',
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'last_name' => $this->customer->last_name,
                    'auto_calculate_iva' => false,
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'auto_calculate_iva' => false,
        ]);
    }

    public function test_can_enable_auto_calculate_iva_field(): void
    {
        // Primero deshabilitar
        $this->customer->auto_calculate_iva = false;
        $this->customer->save();

        $response = $this->patchJson("/api/customers/{$this->customer->id}/auto-calculate-iva", [
            'auto_calculate_iva' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Configuración de IVA actualizada exitosamente',
                'customer' => [
                    'id' => $this->customer->id,
                    'auto_calculate_iva' => true,
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'auto_calculate_iva' => true,
        ]);
    }

    public function test_validates_auto_calculate_iva_field(): void
    {
        $response = $this->patchJson("/api/customers/{$this->customer->id}/auto-calculate-iva", [
            'auto_calculate_iva' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['auto_calculate_iva']);
    }

    public function test_requires_auto_calculate_iva_field(): void
    {
        $response = $this->patchJson("/api/customers/{$this->customer->id}/auto-calculate-iva", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['auto_calculate_iva']);
    }

    public function test_returns_404_for_nonexistent_customer(): void
    {
        $response = $this->patchJson("/api/customers/99999/auto-calculate-iva", [
            'auto_calculate_iva' => true,
        ]);

        $response->assertStatus(404)
            ->assertJson(['message' => 'Customer not found']);
    }

    public function test_can_create_customer_with_auto_calculate_iva_field(): void
    {
        $data = [
            'dni' => 12345678,
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'auto_calculate_iva' => false,
        ];

        $response = $this->postJson('/api/customers', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'dni' => 12345678,
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@example.com',
            'auto_calculate_iva' => false,
        ]);
    }

    public function test_can_update_customer_with_auto_calculate_iva_field(): void
    {
        $data = [
            'dni' => $this->customer->dni,
            'name' => $this->customer->name,
            'last_name' => $this->customer->last_name,
            'email' => $this->customer->email,
            'auto_calculate_iva' => false,
        ];

        $response = $this->putJson("/api/customers/{$this->customer->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'auto_calculate_iva' => false,
        ]);
    }
}
