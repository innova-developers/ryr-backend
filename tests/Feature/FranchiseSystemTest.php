<?php

namespace Tests\Feature;

use App\Franchise;
use App\SuperAdmin;
use App\Services\FranchiseDatabaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FranchiseSystemTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;
    private Franchise $franchise;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->superAdmin = SuperAdmin::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        
        $this->franchise = Franchise::factory()->create([
            'name' => 'Franquicia Test',
            'code' => 'TEST',
            'database_name' => 'franchise_test',
            'is_active' => true,
        ]);
        
        Sanctum::actingAs($this->superAdmin);
    }

    public function test_can_list_franchises(): void
    {
        $response = $this->getJson('/api/super-admin/franchises');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'database_name',
                            'is_active',
                            'created_at',
                        ]
                    ]
                ]
            ]);
    }

    public function test_can_create_franchise(): void
    {
        $franchiseData = [
            'name' => 'Nueva Franquicia',
            'code' => 'NUEVA',
            'database_name' => 'franchise_nueva',
            'description' => 'Descripción de la nueva franquicia',
            'address' => 'Dirección 123',
            'phone' => '+54 11 1234-5678',
            'email' => 'nueva@franquicia.com',
            'contact_person' => 'Juan Pérez',
        ];

        // Mock del servicio para evitar crear bases de datos reales en tests
        $this->mock(FranchiseDatabaseService::class, function ($mock) {
            $mock->shouldReceive('createFranchiseDatabase')
                ->andReturn(true);
        });

        $response = $this->postJson('/api/super-admin/franchises', $franchiseData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Franchise created successfully',
            ]);

        $this->assertDatabaseHas('franchises', [
            'name' => 'Nueva Franquicia',
            'code' => 'NUEVA',
            'database_name' => 'franchise_nueva',
        ]);
    }

    public function test_can_show_franchise(): void
    {
        $response = $this->getJson("/api/super-admin/franchises/{$this->franchise->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'franchise' => [
                        'id',
                        'name',
                        'code',
                        'database_name',
                        'is_active',
                    ],
                    'database_stats',
                ]
            ]);
    }

    public function test_can_update_franchise(): void
    {
        $updateData = [
            'name' => 'Franquicia Actualizada',
            'description' => 'Nueva descripción',
        ];

        $response = $this->putJson("/api/super-admin/franchises/{$this->franchise->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Franchise updated successfully',
            ]);

        $this->assertDatabaseHas('franchises', [
            'id' => $this->franchise->id,
            'name' => 'Franquicia Actualizada',
            'description' => 'Nueva descripción',
        ]);
    }

    public function test_can_activate_franchise(): void
    {
        $this->franchise->update(['is_active' => false]);

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/activate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Franchise activated successfully',
            ]);

        $this->assertDatabaseHas('franchises', [
            'id' => $this->franchise->id,
            'is_active' => true,
        ]);
    }

    public function test_can_deactivate_franchise(): void
    {
        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/deactivate");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Franchise deactivated successfully',
            ]);

        $this->assertDatabaseHas('franchises', [
            'id' => $this->franchise->id,
            'is_active' => false,
        ]);
    }

    public function test_can_access_franchise(): void
    {
        // Asegurar que la franquicia esté activa
        $this->franchise->activate();

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/access");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Access granted to franchise',
            ])
            ->assertJsonStructure([
                'data' => [
                    'franchise',
                    'access_url',
                ]
            ]);
    }

    public function test_can_get_franchise_stats(): void
    {
        $response = $this->getJson("/api/super-admin/franchises/{$this->franchise->id}/stats");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'franchise',
                    'stats',
                ]
            ]);
    }

    public function test_can_get_dashboard(): void
    {
        $response = $this->getJson('/api/super-admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'global_stats',
                    'franchises',
                    'recent_activity',
                ]
            ]);
    }

    public function test_can_get_consolidated_report(): void
    {
        $response = $this->getJson('/api/super-admin/dashboard/consolidated-report');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'franchises',
                    'generated_at',
                ]
            ]);
    }

    public function test_validates_franchise_creation(): void
    {
        $response = $this->postJson('/api/super-admin/franchises', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'database_name']);
    }

    public function test_validates_unique_franchise_code(): void
    {
        $response = $this->postJson('/api/super-admin/franchises', [
            'name' => 'Test Franchise',
            'code' => $this->franchise->code, // Código duplicado
            'database_name' => 'franchise_test_unique',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_filter_franchises_by_status(): void
    {
        // Crear franquicia inactiva
        Franchise::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/super-admin/franchises?is_active=true');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        foreach ($data as $franchise) {
            $this->assertTrue($franchise['is_active']);
        }
    }

    public function test_can_search_franchises(): void
    {
        $response = $this->getJson('/api/super-admin/franchises?search=Test');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertGreaterThan(0, count($data));
    }
}
