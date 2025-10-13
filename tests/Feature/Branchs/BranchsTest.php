<?php

namespace Tests\Feature\Branchs;

use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $branch = Branch::factory()->create();
        $this->admin = User::factory()->create([
            'role' => 'administrador',
            'branch_id' => $branch->id,
        ]);
        $this->actingAs($this->admin, 'sanctum');
    }

    public function test_index_returns_branches()
    {
        Branch::factory()->count(2)->create();
        $response = $this->getJson('/api/branches');
        $response->assertOk();

        $data = $response->json();
        if (isset($data['data'])) {
            // Con paginación
            $this->assertGreaterThanOrEqual(3, count($data['data']));
        } else {
            // Sin paginación
            $this->assertGreaterThanOrEqual(3, count($data));
        }
    }

    public function test_store_creates_branch()
    {
        $data = [
            'name' => 'Sucursal Test',
            'address' => 'Calle 123',
            'phone' => '123456',
            'secondary_phone' => '654321',
            'schedule' => 'Lun a Vie 9-18',
        ];
        $response = $this->postJson('/api/branches', $data);
        $response->assertCreated()->assertJsonFragment(['name' => 'Sucursal Test']);
        $this->assertDatabaseHas('branches', ['name' => 'Sucursal Test']);
    }

    public function test_show_returns_branch()
    {
        $branch = Branch::factory()->create();
        $response = $this->getJson("/api/branches/{$branch->id}");
        $response->assertOk()->assertJsonFragment(['id' => $branch->id]);
    }

    public function test_update_modifies_branch()
    {
        $branch = Branch::factory()->create();
        $data = [
            'name' => 'Sucursal Editada',
            'address' => 'Nueva dirección',
            'phone' => '654321',
            'secondary_phone' => '123456',
            'schedule' => 'Sab 10-14',
        ];
        $response = $this->putJson("/api/branches/{$branch->id}", $data);
        $response->assertOk()->assertJsonFragment(['name' => 'Sucursal Editada']);
        $this->assertDatabaseHas('branches', ['name' => 'Sucursal Editada']);
    }

    public function test_destroy_deletes_branch()
    {
        $branch = Branch::factory()->create();
        $response = $this->deleteJson("/api/branches/{$branch->id}");
        $response->assertOk()->assertJson(['message' => 'Sucursal eliminada']);
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_can_create_branch_with_secondary_phone()
    {
        $data = [
            'name' => 'Sucursal con Teléfono Secundario',
            'address' => 'Calle Secundaria 456',
            'phone' => '111111111',
            'secondary_phone' => '222222222',
            'schedule' => 'Lun a Dom 24h',
        ];

        $response = $this->postJson('/api/branches', $data);
        $response->assertCreated();

        $this->assertDatabaseHas('branches', [
            'name' => 'Sucursal con Teléfono Secundario',
            'secondary_phone' => '222222222',
        ]);
    }

    public function test_can_update_branch_secondary_phone()
    {
        $branch = Branch::factory()->create(['secondary_phone' => '111111111']);

        $data = [
            'name' => $branch->name,
            'address' => $branch->address,
            'phone' => $branch->phone,
            'secondary_phone' => '999999999',
            'schedule' => $branch->schedule,
        ];

        $response = $this->putJson("/api/branches/{$branch->id}", $data);
        $response->assertOk();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'secondary_phone' => '999999999',
        ]);
    }
}
