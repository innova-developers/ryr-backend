<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domain\Enums\UserRole;
use App\Domain\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    public static function loginProvider(): array
    {
        return [
            ['admin@example.com', 'password', UserRole::ADMINISTRADOR->value],
            ['cadete@example.com', 'password', UserRole::CADETE->value],
            ['mostrador@example.com', 'password', UserRole::MOSTRADOR->value],
            ['cliente@example.com', 'password', UserRole::CLIENTE->value],
        ];
    }

    /**
     * @dataProvider loginProvider
     */
    public function test_login_usuarios_con_roles($email, $password, $role)
    {
        $response = $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'token', 'user'])
            ->assertJsonPath('user.role', $role);
    }
}

