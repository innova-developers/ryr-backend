<?php

namespace Tests\Feature;

use App\SuperAdmin;
use App\Franchise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;
    private Franchise $franchise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->superAdmin = SuperAdmin::factory()->create([
            'name' => 'Super Admin Test',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        
        $this->franchise = Franchise::factory()->create([
            'name' => 'Franquicia Test',
            'code' => 'TEST',
            'database_name' => 'franchise_test',
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    public function test_can_login_as_super_admin(): void
    {
        $response = $this->postJson('/api/super-admin/login', [
            'email' => 'superadmin@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login exitoso',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'token',
                    'user_type',
                ]
            ]);

        $this->assertEquals('super_admin', $response->json('data.user_type'));
    }

    public function test_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/super-admin/login', [
            'email' => 'superadmin@test.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_cannot_login_with_inactive_account(): void
    {
        $this->superAdmin->update(['is_active' => false]);

        $response = $this->postJson('/api/super-admin/login', [
            'email' => 'superadmin@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Cuenta desactivada',
            ]);
    }

    public function test_can_logout(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson('/api/super-admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout exitoso',
            ]);
    }

    public function test_can_get_profile(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/super-admin/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'permissions',
                ]
            ]);
    }

    public function test_can_get_available_franchises(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/super-admin/franchises/available');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'logo_url',
                        'url',
                    ]
                ]
            ]);
    }

    public function test_can_select_franchise(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/select");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Franquicia seleccionada exitosamente',
            ])
            ->assertJsonStructure([
                'data' => [
                    'franchise',
                    'access_url',
                ]
            ]);
    }

    public function test_cannot_select_inactive_franchise(): void
    {
        $this->franchise->update(['is_active' => false]);
        
        Sanctum::actingAs($this->superAdmin);

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/select");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Franquicia no disponible',
            ]);
    }

    public function test_can_get_current_franchise(): void
    {
        Sanctum::actingAs($this->superAdmin);
        
        // Primero seleccionar una franquicia
        $selectResponse = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/select");
        $selectResponse->assertStatus(200);

        $response = $this->getJson('/api/super-admin/franchises/current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'franchise',
                    'access_url',
                ]
            ]);
    }

    public function test_returns_404_when_no_franchise_selected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/super-admin/franchises/current');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No hay franquicia seleccionada',
            ]);
    }

    public function test_validates_login_required_fields(): void
    {
        $response = $this->postJson('/api/super-admin/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
