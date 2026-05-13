<?php

namespace Tests\Feature\Franchises;

use App\Shared\Models\Branch;
use App\Shared\Models\Franchise;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $franchiseAdmin;
    private Franchise $franchise;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);

        $this->franchise = Franchise::factory()->create([
            'name' => 'Franquicia Test',
            'slug' => 'franquicia-test',
            'commission_percentage_to_matrix' => 15.00,
        ]);

        $this->franchiseAdmin = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $branch->id,
            'franchise_id' => $this->franchise->id,
        ]);
    }

    public function test_matrix_admin_can_list_all_franchises()
    {
        Franchise::factory()->count(3)->create();
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson('/api/franchises');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertGreaterThanOrEqual(4, count($data['data']));
    }

    public function test_franchise_admin_only_sees_own_franchise()
    {
        Franchise::factory()->count(3)->create();
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $response = $this->getJson('/api/franchises');
        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data['data']);
    }

    public function test_matrix_admin_can_create_franchise()
    {
        $this->actingAs($this->admin, 'sanctum');

        $data = [
            'name' => 'Nueva Franquicia',
            'address' => 'Calle Falsa 123',
            'phone' => '1234567890',
            'email' => 'nueva@franquicia.com',
            'commission_percentage_to_matrix' => 20.00,
            'status' => 'active',
            'contract_start_date' => '2026-01-01',
        ];

        $response = $this->postJson('/api/franchises', $data);
        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Nueva Franquicia']);

        $this->assertDatabaseHas('franchises', ['name' => 'Nueva Franquicia']);
    }

    public function test_franchise_admin_cannot_create_franchise()
    {
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $data = [
            'name' => 'Intento Crear',
            'commission_percentage_to_matrix' => 10,
        ];

        $response = $this->postJson('/api/franchises', $data);
        $response->assertForbidden();
    }

    public function test_matrix_admin_can_view_any_franchise()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchise->id}");
        $response->assertOk()
            ->assertJsonFragment(['name' => 'Franquicia Test']);
    }

    public function test_franchise_admin_can_view_own_franchise()
    {
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $response = $this->getJson("/api/franchises/{$this->franchise->id}");
        $response->assertOk()
            ->assertJsonFragment(['name' => 'Franquicia Test']);
    }

    public function test_franchise_admin_cannot_view_other_franchise()
    {
        $other = Franchise::factory()->create();
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $response = $this->getJson("/api/franchises/{$other->id}");
        $response->assertForbidden();
    }

    public function test_matrix_admin_can_update_franchise()
    {
        $this->actingAs($this->admin, 'sanctum');

        $data = [
            'name' => 'Franquicia Editada',
            'commission_percentage_to_matrix' => 25.00,
            'status' => 'active',
        ];

        $response = $this->putJson("/api/franchises/{$this->franchise->id}", $data);
        $response->assertOk()
            ->assertJsonFragment(['name' => 'Franquicia Editada']);

        $this->assertDatabaseHas('franchises', [
            'id' => $this->franchise->id,
            'name' => 'Franquicia Editada',
        ]);
    }

    public function test_franchise_admin_cannot_update_franchise()
    {
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $data = [
            'name' => 'Intento Editar',
            'commission_percentage_to_matrix' => 5,
        ];

        $response = $this->putJson("/api/franchises/{$this->franchise->id}", $data);
        $response->assertForbidden();
    }

    public function test_matrix_admin_can_delete_franchise()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->deleteJson("/api/franchises/{$this->franchise->id}");
        $response->assertOk()
            ->assertJson(['message' => 'Franquicia eliminada']);

        $this->assertSoftDeleted('franchises', ['id' => $this->franchise->id]);
    }

    public function test_franchise_admin_cannot_delete_franchise()
    {
        $this->actingAs($this->franchiseAdmin, 'sanctum');

        $response = $this->deleteJson("/api/franchises/{$this->franchise->id}");
        $response->assertForbidden();
    }

    public function test_create_franchise_validates_required_name()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/franchises', [
            'commission_percentage_to_matrix' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_franchise_validates_unique_slug()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/franchises', [
            'name' => 'Otra Franquicia',
            'slug' => 'franquicia-test',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_franchise_validates_percentage_range()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/franchises', [
            'name' => 'Test',
            'commission_percentage_to_matrix' => 150,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_franchise_auto_generates_slug()
    {
        $this->actingAs($this->admin, 'sanctum');

        $response = $this->postJson('/api/franchises', [
            'name' => 'Mi Nueva Franquicia',
            'commission_percentage_to_matrix' => 10,
        ]);

        $response->assertCreated();
        $franchise = Franchise::where('name', 'Mi Nueva Franquicia')->first();
        $this->assertNotNull($franchise->slug);
        $this->assertStringContainsString('mi-nueva-franquicia', $franchise->slug);
    }

    public function test_unauthenticated_user_cannot_access_franchises()
    {
        $response = $this->getJson('/api/franchises');
        $response->assertUnauthorized();
    }

    public function test_cadete_cannot_access_franchises()
    {
        $cadete = User::factory()->create(['role' => 'cadete']);
        $this->actingAs($cadete, 'sanctum');

        $response = $this->getJson('/api/franchises');
        $response->assertForbidden();
    }

    public function test_list_franchises_with_search_filter()
    {
        $this->actingAs($this->admin, 'sanctum');
        Franchise::factory()->create(['name' => 'Zona Norte']);
        Franchise::factory()->create(['name' => 'Zona Sur']);

        $response = $this->getJson('/api/franchises?search=Norte');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_list_franchises_with_status_filter()
    {
        $this->actingAs($this->admin, 'sanctum');
        Franchise::factory()->create(['status' => 'suspended']);

        $response = $this->getJson('/api/franchises?status=suspended');
        $response->assertOk();

        $data = $response->json('data');
        foreach ($data as $franchise) {
            $this->assertEquals('suspended', $franchise['status']);
        }
    }

    public function test_franchise_has_owner_relationship()
    {
        $owner = User::factory()->create(['role' => 'administrador']);
        $franchise = Franchise::factory()->create(['owner_user_id' => $owner->id]);

        $this->actingAs($this->admin, 'sanctum');
        $response = $this->getJson("/api/franchises/{$franchise->id}");
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('owner', $data);
        $this->assertEquals($owner->id, $data['owner']['id']);
    }
}
