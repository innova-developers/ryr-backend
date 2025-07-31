<?php

namespace Tests\Feature\Locations;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetLocationsWithFiltersTest extends TestCase
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

    public function test_can_get_locations_without_filters(): void
    {
        // Crear algunas locations
        Location::factory()->count(3)->create();

        $response = $this->getJson('/api/locations');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_can_get_locations_with_search_filter(): void
    {
        // Crear locations con datos específicos
        Location::factory()->create(['name' => 'Sucursal Centro', 'address' => 'Av. Principal 123']);
        Location::factory()->create(['name' => 'Sucursal Norte', 'address' => 'Calle Norte 456']);
        Location::factory()->create(['name' => 'Sucursal Sur', 'address' => 'Av. Sur 789']);

        $response = $this->getJson('/api/locations?search=Centro');

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

    public function test_can_get_locations_with_pagination(): void
    {
        // Crear más locations de las que caben en una página
        Location::factory()->count(15)->create();

        $response = $this->getJson('/api/locations?page=1&per_page=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(5, $data['data']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertEquals(15, $data['pagination']['total']);
    }

    public function test_can_get_locations_with_sorting(): void
    {
        // Crear locations con nombres específicos para ordenamiento
        Location::factory()->create(['name' => 'CCC']);
        Location::factory()->create(['name' => 'AAA']);
        Location::factory()->create(['name' => 'BBB']);

        $response = $this->getJson('/api/locations?sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $locations = $data['data'];
        } else {
            // Sin paginación
            $locations = $data;
        }

        // Verificar que están ordenados alfabéticamente
        $names = array_column($locations, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function test_can_get_locations_with_all_filters(): void
    {
        // Crear locations para probar todos los filtros
        Location::factory()->create(['name' => 'Sucursal ABC', 'address' => 'Dirección ABC']);
        Location::factory()->create(['name' => 'Oficina ABC', 'address' => 'Calle ABC']);
        Location::factory()->create(['name' => 'Sucursal XYZ', 'address' => 'Dirección XYZ']);

        $response = $this->getJson('/api/locations?search=ABC&page=1&per_page=2&sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);

        // Verificar que solo devuelve locations con "ABC" en el nombre
        foreach ($data['data'] as $location) {
            $this->assertStringContainsString('ABC', $location['name']);
        }
    }

    public function test_validates_sort_direction(): void
    {
        $response = $this->getJson('/api/locations?sort_direction=invalid');

        $response->assertStatus(200); // Debería funcionar con ordenamiento por defecto
    }

    public function test_limits_per_page_to_maximum(): void
    {
        // Crear muchas locations
        Location::factory()->count(200)->create();

        $response = $this->getJson('/api/locations?per_page=150'); // Más del límite de 100

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(100, $data['pagination']['per_page']); // Debería limitarse a 100
        $this->assertEquals(200, $data['pagination']['total']);
    }

    public function test_search_by_address(): void
    {
        Location::factory()->create(['address' => 'Av. Principal 123']);
        Location::factory()->create(['address' => 'Calle Secundaria 456']);
        Location::factory()->create(['address' => 'Av. Principal 789']);

        $response = $this->getJson('/api/locations?search=Principal');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $locations = $data['data'];
        } else {
            $locations = $data;
        }

        // Verificar que devuelve locations con "Principal" en la dirección
        foreach ($locations as $location) {
            $this->assertStringContainsString('Principal', $location['address']);
        }
    }

    public function test_search_by_origin(): void
    {
        Location::factory()->create(['origin' => 'Buenos Aires']);
        Location::factory()->create(['origin' => 'Córdoba']);
        Location::factory()->create(['origin' => 'Buenos Aires Norte']);

        $response = $this->getJson('/api/locations?search=Buenos Aires');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $locations = $data['data'];
        } else {
            $locations = $data;
        }

        // Verificar que devuelve locations con "Buenos Aires" en el origen
        foreach ($locations as $location) {
            $this->assertStringContainsString('Buenos Aires', $location['origin']);
        }
    }
}
