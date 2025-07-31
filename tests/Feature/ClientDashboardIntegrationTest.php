<?php

namespace Tests\Feature;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Customer $customer;
    private string $token;

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
            'mobile' => '2915662430',
        ]);

        // Generar token
        $this->token = $this->clientUser->createToken('test-token')->plainTextToken;
    }

    public function test_client_can_access_profile_with_valid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => 'Juan',
                    'last_name' => 'Pérez',
                    'email' => 'cliente@test.com',
                    'mobile' => '2915662430',
                ],
                'user' => [
                    'id' => $this->clientUser->id,
                    'name' => $this->clientUser->name,
                    'email' => 'cliente@test.com',
                ],
            ]);
    }

    public function test_client_can_update_profile_with_valid_token()
    {
        $updateData = [
            'name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@test.com',
            'mobile' => '2915662431',
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->putJson('/api/client/profile', $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'customer' => [
                    'id' => $this->customer->id,
                    'name' => 'María',
                    'last_name' => 'González',
                    'email' => 'maria@test.com',
                    'mobile' => '2915662431',
                ],
            ]);
    }

    public function test_client_can_access_shipments_with_valid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/shipments');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'shipments',
            ]);
    }

    public function test_client_can_access_account_balance_with_valid_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/account-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'currency' => 'ARS',
            ])
            ->assertJsonStructure([
                'success',
                'balance',
                'currency',
            ]);
    }

    public function test_non_client_user_cannot_access_dashboard()
    {
        // Crear usuario admin
        $adminUser = User::factory()->create([
            'role' => UserRole::ADMINISTRADOR,
        ]);

        $adminToken = $adminUser->createToken('admin-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/client/profile');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/client/profile');

        $response->assertStatus(401);
    }

    public function test_client_can_access_current_account_transactions()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/current-account/transactions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'transactions',
            ]);
    }

    public function test_client_can_access_current_account_balance()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/current-account/balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'currency' => 'ARS',
            ])
            ->assertJsonStructure([
                'success',
                'balance',
                'currency',
                'last_transaction_date',
            ]);
    }
}
