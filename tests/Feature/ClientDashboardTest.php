<?php

namespace Tests\Feature;

use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario cliente
        $this->clientUser = User::factory()->create([
            'role' => UserRole::CLIENTE,
            'email' => 'cliente@test.com',
        ]);

        // Crear cliente
        $this->customer = Customer::factory()->create([
            'email' => 'cliente@test.com',
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'phone' => '2915662430',
        ]);
    }

    public function test_client_can_get_profile()
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->getJson('/api/client/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => 'Juan',
                    'last_name' => 'Pérez',
                    'email' => 'cliente@test.com',
                    'phone' => '2915662430',
                ],
                'user' => [
                    'id' => $this->clientUser->id,
                    'name' => $this->clientUser->name,
                    'email' => 'cliente@test.com',
                ],
            ]);
    }

    public function test_client_can_update_profile()
    {
        Sanctum::actingAs($this->clientUser);

        $updateData = [
            'name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@test.com',
            'phone' => '2915662431',
        ];

        $response = $this->putJson('/api/client/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => 'María',
                    'last_name' => 'González',
                    'email' => 'maria@test.com',
                    'phone' => '2915662431',
                ],
            ]);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@test.com',
                    'phone' => '2915662431',
        ]);
    }

    public function test_client_can_get_shipments()
    {
        Sanctum::actingAs($this->clientUser);

        // Crear destinos
        $origin = Destination::factory()->create(['origin' => 'Buenos Aires', 'destination' => 'Córdoba']);
        $destination = Destination::factory()->create(['origin' => 'Buenos Aires', 'destination' => 'Mendoza']);

        // Crear locations para las comisiones
        $originLocation = \App\Shared\Models\Location::factory()->create(['name' => 'Buenos Aires']);
        $destinationLocation = \App\Shared\Models\Location::factory()->create(['name' => 'Córdoba']);

        // Crear comisiones para el cliente
        $commission1 = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::ENTREGADO,
            'total' => 15000.00,
        ]);

        $commission2 = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'status' => CommissionStatus::PENDIENTE,
            'total' => 8000.00,
        ]);

        // Crear items para las comisiones
        CommissionItem::factory()->create([
            'commission_id' => $commission1->id,
            'type' => CommissionItemType::ORDINARIA,
            'quantity' => 2,
            'unit_price' => 5000.00,
            'subtotal' => 10000.00,
        ]);

        CommissionItem::factory()->create([
            'commission_id' => $commission1->id,
            'type' => CommissionItemType::EXTRAORDINARIA,
            'quantity' => 1,
            'unit_price' => 5000.00,
            'subtotal' => 5000.00,
        ]);

        $response = $this->getJson('/api/client/shipments');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'shipments' => [
                    '*' => [
                        'id',
                        'tracking_number',
                        'status',
                        'origin',
                        'destination',
                        'total',
                        'created_at',
                        'items' => [
                            '*' => [
                                'id',
                                'type',
                                'quantity',
                                'unit_price',
                                'subtotal',
                            ],
                        ],
                    ],
                ],
            ]);

        // Verificar que solo devuelve las comisiones del cliente
        $shipments = $response->json('shipments');
        $this->assertCount(2, $shipments);
        $this->assertEquals('RYR' . str_pad($commission1->id, 9, '0', STR_PAD_LEFT), $shipments[0]['tracking_number']);
    }

    public function test_client_can_get_account_balance()
    {
        Sanctum::actingAs($this->clientUser);

        // Crear transacciones de cuenta corriente
        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'amount' => 10000.00,
            'balance' => 10000.00,
            'transaction_date' => now()->subDay(),
        ]);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'amount' => 15000.50,
            'balance' => 25000.50,
            'transaction_date' => now(),
        ]);

        // Crear transacción para otro cliente (no debe aparecer)
        CurrentAccount::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
            'amount' => 5000.00,
        ]);

        $response = $this->getJson('/api/client/account-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'balance' => 25000.50,
                'currency' => 'ARS',
            ]);
    }

    public function test_non_client_user_cannot_access_dashboard()
    {
        // Crear usuario admin
        $adminUser = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->getJson('/api/client/profile');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->getJson('/api/client/profile');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'No autenticado.',
                'error' => 'Unauthenticated',
            ]);
    }

    public function test_client_without_customer_record_returns_404()
    {
        // Crear usuario cliente sin cliente asociado
        $clientWithoutCustomer = User::factory()->create([
            'role' => UserRole::CLIENTE,
            'email' => 'sincliente@test.com',
        ]);

        Sanctum::actingAs($clientWithoutCustomer);

        $response = $this->getJson('/api/client/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer' => null,
                'user' => [
                    'id' => $clientWithoutCustomer->id,
                    'name' => $clientWithoutCustomer->name,
                    'email' => 'sincliente@test.com',
                ],
            ]);
    }

    public function test_update_profile_validation()
    {
        Sanctum::actingAs($this->clientUser);

        $response = $this->putJson('/api/client/profile', [
            'name' => '',
            'last_name' => '',
            'email' => 'invalid-email',
            'phone' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonValidationErrors(['name', 'last_name', 'email', 'phone']);
    }
}
