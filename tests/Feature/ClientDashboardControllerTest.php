<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\ClientDashboardController;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ClientDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private ClientDashboardController $controller;
    private User $clientUser;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ClientDashboardController();

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

    public function test_get_profile_returns_correct_data()
    {
        // Simular autenticación
        \Illuminate\Support\Facades\Auth::shouldReceive('user')
            ->andReturn($this->clientUser);

        $response = $this->controller->getProfile();

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($this->customer->id, $data['customer']['id']);
        $this->assertEquals('Juan', $data['customer']['name']);
        $this->assertEquals('Pérez', $data['customer']['last_name']);
        $this->assertEquals('cliente@test.com', $data['customer']['email']);
        $this->assertEquals('2915662430', $data['customer']['phone']);
    }

    public function test_update_profile_updates_customer_data()
    {
        $updateData = [
            'name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@test.com',
            'phone' => '2915662431',
        ];

        $request = Request::create('/api/client/profile', 'PUT', $updateData);

        // Simular autenticación
        \Illuminate\Support\Facades\Auth::shouldReceive('user')
            ->andReturn($this->clientUser);

        $response = $this->controller->updateProfile($request);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Perfil actualizado correctamente', $data['message']);
        $this->assertEquals('María', $data['customer']['name']);
        $this->assertEquals('González', $data['customer']['last_name']);
        $this->assertEquals('maria@test.com', $data['customer']['email']);
        $this->assertEquals('2915662431', $data['customer']['phone']);

        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'name' => 'María',
            'last_name' => 'González',
            'email' => 'maria@test.com',
            'phone' => '2915662431',
        ]);
    }
}
