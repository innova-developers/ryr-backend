<?php

namespace Tests\Feature\Franchises;

use App\Shared\Models\Branch;
use App\Shared\Models\Franchise;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FranchiseMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = Branch::factory()->create();
    }

    public function test_admin_franquicia_role_can_access_isAdmin_routes()
    {
        $franchise = Franchise::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $this->branch->id,
            'franchise_id' => $franchise->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertOk();
    }

    public function test_admin_franquicia_role_can_access_adminOrCadete_routes()
    {
        $franchise = Franchise::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $this->branch->id,
            'franchise_id' => $franchise->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/commissions');
        $response->assertOk();
    }

    public function test_cliente_role_cannot_access_franchise_routes()
    {
        $user = User::factory()->create([
            'role' => 'cliente',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertForbidden();
    }

    public function test_cobrador_role_cannot_access_franchise_routes()
    {
        $user = User::factory()->create([
            'role' => 'cobrador',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertForbidden();
    }

    public function test_cadete_role_cannot_access_franchise_routes()
    {
        $user = User::factory()->create([
            'role' => 'cadete',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertForbidden();
    }

    public function test_mostrador_can_access_franchise_routes()
    {
        $user = User::factory()->create([
            'role' => 'mostrador',
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertOk();
    }

    public function test_franchise_scope_middleware_sets_scope_for_franchise_admin()
    {
        $franchise = Franchise::factory()->create();
        $user = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $this->branch->id,
            'franchise_id' => $franchise->id,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($franchise->id, $data[0]['id']);
    }

    public function test_admin_franquicia_without_franchise_rejected_by_scope_middleware()
    {
        $user = User::factory()->create([
            'role' => 'admin_franquicia',
            'branch_id' => $this->branch->id,
            'franchise_id' => null,
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->getJson('/api/franchises');
        $response->assertForbidden()
            ->assertJson(['message' => 'Usuario sin franquicia asignada']);
    }
}
