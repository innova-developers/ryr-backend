<?php

namespace Tests\Feature\Transports;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTransportsWithFiltersTest extends TestCase
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

    public function test_can_get_transports_without_filters(): void
    {
        // Crear algunos transports
        Transport::factory()->count(3)->create();

        $response = $this->getJson('/api/transports');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_can_get_transports_with_search_filter(): void
    {
        // Crear transports con datos específicos
        Transport::factory()->create(['plate' => 'ABC123', 'description' => 'Camión grande']);
        Transport::factory()->create(['plate' => 'XYZ789', 'description' => 'Furgón pequeño']);
        Transport::factory()->create(['plate' => 'DEF456', 'description' => 'Camión mediano']);

        $response = $this->getJson('/api/transports?search=ABC');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $this->assertCount(1, $data['data']);
            $this->assertEquals('ABC123', $data['data'][0]['plate']);
        } else {
            // Sin paginación
            $this->assertCount(1, $data);
            $this->assertEquals('ABC123', $data[0]['plate']);
        }
    }

    public function test_can_get_transports_with_pagination(): void
    {
        // Crear más transports de los que caben en una página
        Transport::factory()->count(15)->create();

        $response = $this->getJson('/api/transports?page=1&per_page=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(5, $data['data']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertEquals(15, $data['pagination']['total']);
    }

    public function test_can_get_transports_with_sorting(): void
    {
        // Crear transports con placas específicas para ordenamiento
        Transport::factory()->create(['plate' => 'CCC']);
        Transport::factory()->create(['plate' => 'AAA']);
        Transport::factory()->create(['plate' => 'BBB']);

        $response = $this->getJson('/api/transports?sort_by=plate&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $transports = $data['data'];
        } else {
            // Sin paginación
            $transports = $data;
        }

        // Verificar que están ordenados alfabéticamente
        $plates = array_column($transports, 'plate');
        $sortedPlates = $plates;
        sort($sortedPlates);
        $this->assertEquals($sortedPlates, $plates);
    }

    public function test_can_get_transports_with_all_filters(): void
    {
        // Crear transports para probar todos los filtros
        Transport::factory()->create(['plate' => 'ABC123', 'description' => 'Camión ABC']);
        Transport::factory()->create(['plate' => 'ABC456', 'description' => 'Furgón ABC']);
        Transport::factory()->create(['plate' => 'XYZ789', 'description' => 'Camión XYZ']);

        $response = $this->getJson('/api/transports?search=ABC&page=1&per_page=2&sort_by=plate&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);

        // Verificar que solo devuelve transports con "ABC" en la placa
        foreach ($data['data'] as $transport) {
            $this->assertStringContainsString('ABC', $transport['plate']);
        }
    }

    public function test_validates_sort_direction(): void
    {
        $response = $this->getJson('/api/transports?sort_direction=invalid');

        $response->assertStatus(200); // Debería funcionar con ordenamiento por defecto
    }

    public function test_limits_per_page_to_maximum(): void
    {
        // Crear muchos transports
        Transport::factory()->count(200)->create();

        $response = $this->getJson('/api/transports?per_page=150'); // Más del límite de 100

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(100, $data['pagination']['per_page']); // Debería limitarse a 100
        $this->assertEquals(200, $data['pagination']['total']);
    }

    public function test_search_by_description(): void
    {
        Transport::factory()->create(['description' => 'Camión de carga pesada']);
        Transport::factory()->create(['description' => 'Furgón de reparto']);
        Transport::factory()->create(['description' => 'Camión de mudanza']);

        $response = $this->getJson('/api/transports?search=camión');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $transports = $data['data'];
        } else {
            $transports = $data;
        }

        // Verificar que devuelve transports con "camión" en la descripción
        foreach ($transports as $transport) {
            $this->assertStringContainsString('Camión', $transport['description']);
        }
    }

    public function test_search_by_phone(): void
    {
        Transport::factory()->create(['phone' => '123456789']);
        Transport::factory()->create(['phone' => '987654321']);
        Transport::factory()->create(['phone' => '555666777']);

        $response = $this->getJson('/api/transports?search=123');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $transports = $data['data'];
        } else {
            $transports = $data;
        }

        // Verificar que devuelve transports con "123" en el teléfono
        foreach ($transports as $transport) {
            $this->assertStringContainsString('123', $transport['phone']);
        }
    }
}
