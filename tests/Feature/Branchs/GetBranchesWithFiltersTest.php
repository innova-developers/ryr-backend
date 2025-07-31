<?php

namespace Tests\Feature\Branchs;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetBranchesWithFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario administrador
        $this->adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR]);
        Sanctum::actingAs($this->adminUser);
    }

    public function test_can_get_branches_without_filters(): void
    {
        // Crear algunas branches
        Branch::factory()->count(3)->create();

        $response = $this->getJson('/api/branches');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_can_get_branches_with_search_filter(): void
    {
        // Crear branches con datos específicos
        Branch::factory()->create(['name' => 'Sucursal Centro', 'address' => 'Av. Principal 123']);
        Branch::factory()->create(['name' => 'Sucursal Norte', 'address' => 'Calle Norte 456']);
        Branch::factory()->create(['name' => 'Sucursal Sur', 'address' => 'Av. Sur 789']);

        $response = $this->getJson('/api/branches?search=Centro');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $this->assertCount(1, $data['data']);
            $this->assertEquals('Sucursal Centro', $data['data'][0]['name']);
        } else {
            // Sin paginación
            $this->assertCount(1, $data);
            $this->assertEquals('Sucursal Centro', $data[0]['name']);
        }
    }

    public function test_can_get_branches_with_pagination(): void
    {
        // Crear más branches de las que caben en una página
        Branch::factory()->count(15)->create();

        $response = $this->getJson('/api/branches?page=1&per_page=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(5, $data['data']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertGreaterThanOrEqual(15, $data['pagination']['total']);
    }

    public function test_can_get_branches_with_sorting(): void
    {
        // Crear branches con nombres específicos para ordenamiento
        Branch::factory()->create(['name' => 'CCC']);
        Branch::factory()->create(['name' => 'AAA']);
        Branch::factory()->create(['name' => 'BBB']);

        $response = $this->getJson('/api/branches?sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $branches = $data['data'];
        } else {
            // Sin paginación
            $branches = $data;
        }

        // Verificar que están ordenados alfabéticamente
        $names = array_column($branches, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function test_can_get_branches_with_all_filters(): void
    {
        // Crear branches para probar todos los filtros
        Branch::factory()->create(['name' => 'Sucursal ABC', 'address' => 'Dirección ABC']);
        Branch::factory()->create(['name' => 'Oficina ABC', 'address' => 'Calle ABC']);
        Branch::factory()->create(['name' => 'Sucursal XYZ', 'address' => 'Dirección XYZ']);

        $response = $this->getJson('/api/branches?search=ABC&page=1&per_page=2&sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);

        // Verificar que solo devuelve branches con "ABC" en el nombre
        foreach ($data['data'] as $branch) {
            $this->assertStringContainsString('ABC', $branch['name']);
        }
    }

    public function test_validates_sort_direction(): void
    {
        $response = $this->getJson('/api/branches?sort_direction=invalid');

        $response->assertStatus(200); // Debería funcionar con ordenamiento por defecto
    }

    public function test_limits_per_page_to_maximum(): void
    {
        // Crear muchas branches
        Branch::factory()->count(200)->create();

        $response = $this->getJson('/api/branches?per_page=150'); // Más del límite de 100

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(100, $data['pagination']['per_page']); // Debería limitarse a 100
        $this->assertGreaterThanOrEqual(200, $data['pagination']['total']);
    }

    public function test_search_by_address(): void
    {
        Branch::factory()->create(['address' => 'Av. Principal 123']);
        Branch::factory()->create(['address' => 'Calle Secundaria 456']);
        Branch::factory()->create(['address' => 'Av. Principal 789']);

        $response = $this->getJson('/api/branches?search=Principal');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $branches = $data['data'];
        } else {
            $branches = $data;
        }

        // Verificar que devuelve branches con "Principal" en la dirección
        foreach ($branches as $branch) {
            $this->assertStringContainsString('Principal', $branch['address']);
        }
    }

    public function test_search_by_phone(): void
    {
        Branch::factory()->create(['phone' => '123456789']);
        Branch::factory()->create(['phone' => '987654321']);
        Branch::factory()->create(['phone' => '123456790']);

        $response = $this->getJson('/api/branches?search=123456');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $branches = $data['data'];
        } else {
            $branches = $data;
        }

        // Verificar que devuelve branches con "123456" en el teléfono
        foreach ($branches as $branch) {
            $this->assertStringContainsString('123456', $branch['phone']);
        }
    }

    public function test_search_by_secondary_phone(): void
    {
        Branch::factory()->create(['secondary_phone' => '111222333']);
        Branch::factory()->create(['secondary_phone' => '444555666']);
        Branch::factory()->create(['secondary_phone' => '111222444']);

        $response = $this->getJson('/api/branches?search=111222');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $branches = $data['data'];
        } else {
            $branches = $data;
        }

        // Verificar que devuelve branches con "111222" en el teléfono secundario
        foreach ($branches as $branch) {
            $this->assertStringContainsString('111222', $branch['secondary_phone']);
        }
    }
}
