<?php

namespace Tests\Feature\Auth;

use App\Shared\Enums\UserRole;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_invalidate_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        $token = $user->createToken('test-token')->plainTextToken;

        // Verificar que el token funciona antes del logout
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/users')
            ->assertStatus(200);

        // Hacer logout
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Sesión cerrada correctamente',
            ]);

        // Verificar que el token se eliminó de la base de datos
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', $token),
        ]);

        // Verificar que el token se eliminó correctamente
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', $token),
        ]);
    }
}
