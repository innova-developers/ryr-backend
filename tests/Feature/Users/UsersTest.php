<?php

namespace Tests\Feature\Users;

use App\Shared\Enums\UserRole;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_admin_can_create_user():void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value]);
        $payload = [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@ejemplo.com',
            'password' => 'password123',
            'role' => UserRole::ADMINISTRADOR->value,
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);

        $response->assertStatus(201)
            ->assertJsonFragment(['email' => 'nuevo@ejemplo.com']);
        $this->assertDatabaseHas('users', ['email' => 'nuevo@ejemplo.com']);
    }

    public function test_non_admin_cannot_create_user():void
    {
        $user = User::factory()->create(['role' => UserRole::MOSTRADOR->value]);
        $payload = [
            'name' => 'Usuario',
            'email' => 'usuario@ejemplo.com',
            'password' => 'password123',
            'role' => UserRole::MOSTRADOR->value,
        ];

        $response = $this->actingAs($user)->postJson('/api/users', $payload);
        $response->assertStatus(403);
        $response->assertJson(['message' => 'No autorizado']);
        $this->assertDatabaseMissing('users', ['email' => 'usuario@ejemplo.com']);
    }

    public function test_admin_cannot_create_user_with_invalid_data():void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value]);
        $payload = [
            'name' => '',
            'email' => 'no-es-un-email',
            'password' => '',
            'role' => 'rol-invalido',
        ];

        $response = $this->actingAs($admin)->postJson('/api/users', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Datos inválidos: The name field is required., The email field must be a valid email address., The password field is required., The selected role is invalid.'
        ]);
    }
}
