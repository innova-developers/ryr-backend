<?php

namespace Tests\Feature;

use App\Franchise;
use App\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FranchiseLogoTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $superAdmin;
    private Franchise $franchise;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('public');
        
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
        
        Sanctum::actingAs($this->superAdmin);
    }

    public function test_can_upload_franchise_logo(): void
    {
        $file = UploadedFile::fake()->image('logo.png', 100, 100);

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/logo", [
            'logo' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logo uploaded successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'logo_url',
                ]
            ]);

        // Verificar que el archivo se guardó (el nombre puede variar)
        $logoPath = $this->franchise->fresh()->logo_path;
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
        
        // Verificar que se actualizó en la base de datos
        $this->assertDatabaseHas('franchises', [
            'id' => $this->franchise->id,
        ]);
        
        $franchise = $this->franchise->fresh();
        $this->assertNotNull($franchise->logo_path);
    }

    public function test_validates_logo_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/logo", [
            'logo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_validates_logo_file_size(): void
    {
        // Crear un archivo de texto grande en lugar de imagen
        $file = UploadedFile::fake()->create('large_file.txt', 3000); // 3MB

        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/logo", [
            'logo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_requires_logo_file(): void
    {
        $response = $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/logo", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_can_get_franchise_settings(): void
    {
        $response = $this->getJson("/api/super-admin/franchises/{$this->franchise->id}/settings");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'franchise',
                    'settings',
                ]
            ]);
    }

    public function test_can_update_franchise_settings(): void
    {
        $settings = [
            'auto_iva' => true,
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'theme' => 'dark',
        ];

        $response = $this->putJson("/api/super-admin/franchises/{$this->franchise->id}/settings", [
            'settings' => $settings,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Settings updated successfully',
            ]);

        $this->assertDatabaseHas('franchises', [
            'id' => $this->franchise->id,
            'settings' => json_encode($settings),
        ]);
    }

    public function test_validates_settings_required(): void
    {
        $response = $this->putJson("/api/super-admin/franchises/{$this->franchise->id}/settings", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_can_get_franchises_for_selector(): void
    {
        $response = $this->getJson('/api/super-admin/franchises/selector');

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
                    ]
                ]
            ]);

        // Verificar que la franquicia de test está incluida
        $franchises = $response->json('data');
        $this->assertCount(1, $franchises);
        $this->assertEquals('Franquicia Test', $franchises[0]['name']);
    }

    public function test_franchise_selector_includes_logo_url(): void
    {
        // Subir un logo primero
        $file = UploadedFile::fake()->image('logo.png', 100, 100);
        $this->postJson("/api/super-admin/franchises/{$this->franchise->id}/logo", [
            'logo' => $file,
        ]);

        $response = $this->getJson('/api/super-admin/franchises/selector');

        $response->assertStatus(200);
        
        $franchiseData = $response->json('data')[0];
        $this->assertNotNull($franchiseData['logo_url']);
        $this->assertStringContainsString('franchises/logos/', $franchiseData['logo_url']);
    }
}
