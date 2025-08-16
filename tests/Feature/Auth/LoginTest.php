<?php

namespace Tests\Feature\Auth;

use App\Shared\Enums\UserRole;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Proveedor de datos para usuarios web (solo administradores).
     */
    public static function webScopeCredentialsProvider(): array
    {
        return [
            'Administrador' => [UserRole::ADMINISTRADOR, true],
            'Cadete' => [UserRole::CADETE, false],
            'Mostrador' => [UserRole::MOSTRADOR, false],
            'Cliente' => [UserRole::CLIENTE, false],
            'Cadete Externo' => [UserRole::CADETE_EXTERNO, false],
        ];
    }

    /**
     * Proveedor de datos para usuarios app (cadetes y cadetes externos).
     */
    public static function appScopeCredentialsProvider(): array
    {
        return [
            'Administrador' => [UserRole::ADMINISTRADOR, false],
            'Cadete' => [UserRole::CADETE, true],
            'Mostrador' => [UserRole::MOSTRADOR, false],
            'Cliente' => [UserRole::CLIENTE, false],
            'Cadete Externo' => [UserRole::CADETE_EXTERNO, true],
        ];
    }

    #[DataProvider('webScopeCredentialsProvider')]
    public function test_login_web_scope_validation($role, $shouldSucceed)
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'scope' => 'web',
        ]);

        if ($shouldSucceed) {
            $response->assertStatus(200)
                ->assertJsonStructure(['success', 'token', 'user'])
                ->assertJsonPath('user.role', $role->value);
        } else {
            $response->assertStatus(403)
                ->assertJson([
                    'error' => 'scope_validation_failed'
                ])
                ->assertJsonFragment([
                    'message' => "El usuario con rol '{$role->value}' no tiene permisos para acceder a la aplicación web. Solo administradores pueden acceder."
                ]);
        }
    }

    #[DataProvider('appScopeCredentialsProvider')]
    public function test_login_app_scope_validation($role, $shouldSucceed)
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'scope' => 'app',
        ]);

        if ($shouldSucceed) {
            $response->assertStatus(200)
                ->assertJsonStructure(['success', 'token', 'user'])
                ->assertJsonPath('user.role', $role->value);
        } else {
            $response->assertStatus(403)
                ->assertJson([
                    'error' => 'scope_validation_failed'
                ])
                ->assertJsonFragment([
                    'message' => "El usuario con rol '{$role->value}' no tiene permisos para acceder a la aplicación móvil. Solo cadetes y cadetes externos pueden acceder."
                ]);
        }
    }

    public function test_login_requires_scope_parameter()
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            // Sin scope
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scope']);
    }

    public function test_login_with_invalid_scope()
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'scope' => 'invalid_scope',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scope']);
    }

    public function test_login_with_invalid_credentials()
    {
        $user = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong_password',
            'scope' => 'web',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_credentials',
                'message' => 'Credenciales inválidas'
            ]);
    }
}
