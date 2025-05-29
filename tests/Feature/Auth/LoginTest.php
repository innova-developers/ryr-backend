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
     * Proveedor de datos usando el factory de User.
     */
    public static function userCredentialsProvider(): array
    {
        return [
            'Administrador' => [UserRole::ADMINISTRADOR],
            'Cadete'        => [UserRole::CADETE],
            'Mostrador'     => [UserRole::MOSTRADOR],
            'Cliente'       => [UserRole::CLIENTE],
        ];
    }

    #[DataProvider('userCredentialsProvider')]
    public function test_login_with_all_roles($role)
    {
        $user = User::factory()->create(['role' => $role]);
        $response = $this->postJson('/api/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'token', 'user'])
            ->assertJsonPath('user.role', $role->value);
    }
}
