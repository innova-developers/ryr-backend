<?php

namespace Tests\Feature\Users;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value]);
        $branch = Branch::factory()->create();
        $payload = [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'password123',
            'role' => UserRole::ADMINISTRADOR->value,
            'branch_id' => $branch->id,
            'base_salary' => 5000.00,
            'commission_percentage' => 15.50,
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);
        $response->assertStatus(201)
            ->assertJsonFragment(['email' => 'nuevo@ejemplo.com', 'branch_id' => $branch->id]);
        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@ejemplo.com', 
            'branch_id' => $branch->id,
            'base_salary' => 5000.00,
            'commission_percentage' => 15.50,
        ]);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $user = User::factory()->create(['role' => UserRole::MOSTRADOR->value]);
        $branch = Branch::factory()->create();
        $payload = [
            'name' => 'Usuario',
            'email' => 'usuario@ejemplo.com',
            'password' => 'password123',
            'role' => UserRole::MOSTRADOR->value,
            'branch_id' => $branch->id,
            'base_salary' => 3000.00,
            'commission_percentage' => 10.00,
        ];

        $response = $this->actingAs($user)->postJson('/api/users', $payload);
        $response->assertStatus(403);
        $response->assertJson(['message' => 'No autorizado']);
        $this->assertDatabaseMissing('users', ['email' => 'usuario@ejemplo.com']);
    }

    public function test_admin_cannot_create_user_with_invalid_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value]);
        $payload = [
            'name' => '',
            'email' => 'no-es-un-email',
            'password' => '',
            'role' => 'rol-invalido',
            'branch_id' => 999, // id inexistente
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Datos inválidos: The name field is required., The email field must be a valid email address., The password field is required., The selected role is invalid., The selected branch id is invalid.',
        ]);
    }

    public function test_admin_can_create_user_with_optional_salary_fields(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value]);
        $branch = Branch::factory()->create();
        $payload = [
            'name' => 'Usuario Sin Salario',
            'email' => 'sin-salario@ejemplo.com',
            'password' => 'password123',
            'role' => UserRole::MOSTRADOR->value,
            'branch_id' => $branch->id,
            // Sin base_salary ni commission_percentage
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);
        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'sin-salario@ejemplo.com',
            'base_salary' => null,
            'commission_percentage' => null,
        ]);
    }

    public function test_admin_cannot_create_user_with_invalid_salary_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value]);
        $branch = Branch::factory()->create();
        $payload = [
            'name' => 'Usuario',
            'email' => 'usuario@ejemplo.com',
            'password' => 'password123',
            'role' => UserRole::MOSTRADOR->value,
            'branch_id' => $branch->id,
            'base_salary' => -1000, // Valor negativo
            'commission_percentage' => 150, // Porcentaje mayor a 100
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);
        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Datos inválidos: The base salary field must be at least 0., The commission percentage field must not be greater than 100.',
        ]);
    }
}
