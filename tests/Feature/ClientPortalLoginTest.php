<?php

namespace Tests\Feature;

use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RC-492 (PORTAL CLIENTE).
 *
 * El portal estaba entero pero era inaccesible: LoginUseCase::validateUserScope()
 * no incluía CLIENTE en el scope "web", así que todo intento de login del cliente
 * moría por scope inválido aunque las credenciales fueran correctas.
 */
class ClientPortalLoginTest extends TestCase
{
    use RefreshDatabase;

    private function clientUser(string $email = 'cliente@example.com'): User
    {
        return User::factory()->create([
            'role' => 'cliente',
            'email' => $email,
            'password' => Hash::make('secreto123'),
            'branch_id' => Branch::factory()->create()->id,
        ]);
    }

    public function test_client_can_login_on_web_scope(): void
    {
        $this->clientUser();

        $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
            'password' => 'secreto123',
            'scope' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'cliente')
            ->assertJsonStructure(['token']);
    }

    public function test_client_cannot_login_on_app_scope(): void
    {
        // El scope "app" es la app de cadetes: el cliente no debe entrar ahí.
        $this->clientUser();

        $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
            'password' => 'secreto123',
            'scope' => 'app',
        ])->assertStatus(403);
    }

    public function test_client_with_wrong_password_is_rejected(): void
    {
        $this->clientUser();

        $this->postJson('/api/login', [
            'email' => 'cliente@example.com',
            'password' => 'incorrecta',
            'scope' => 'web',
        ])->assertStatus(401);
    }

    public function test_profile_resolves_customer_by_user_id(): void
    {
        $user = $this->clientUser('vinculado@example.com');
        $customer = Customer::factory()->create([
            'user_id' => $user->id,
            'email' => 'otro-email-distinto@example.com',
        ]);

        $this->actingAs($user)
            ->getJson('/api/client/profile')
            ->assertOk()
            ->assertJsonPath('customer.id', $customer->id);
    }

    public function test_profile_falls_back_to_email_match(): void
    {
        // Base histórica: 2931 clientes tienen user_id apuntando al admin que los
        // cargó, así que el vínculo fuerte no existe y hay que caer al email.
        $user = $this->clientUser('porcorreo@example.com');
        $admin = User::factory()->create(['role' => 'administrador']);
        $customer = Customer::factory()->create([
            'user_id' => $admin->id,
            'email' => 'porcorreo@example.com',
        ]);

        $this->actingAs($user)
            ->getJson('/api/client/profile')
            ->assertOk()
            ->assertJsonPath('customer.id', $customer->id);
    }

    public function test_non_client_role_cannot_use_client_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'administrador']);

        $this->actingAs($admin)
            ->getJson('/api/client/profile')
            ->assertStatus(403);
    }
}
