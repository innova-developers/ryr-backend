<?php

namespace Tests\Feature\Customers;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetCustomersWithFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario administrador
        $this->adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => null]);
        Sanctum::actingAs($this->adminUser);

        // Crear sucursal
        $branch = Branch::factory()->create();
    }

    public function test_can_get_customers_without_filters(): void
    {
        // Crear algunos customers
        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_can_get_customers_with_search_filter(): void
    {
        // Crear customers con datos específicos
        Customer::factory()->create(['name' => 'Juan Pérez', 'email' => 'juan@example.com']);
        Customer::factory()->create(['name' => 'María García', 'email' => 'maria@example.com']);
        Customer::factory()->create(['name' => 'Carlos López', 'email' => 'carlos@example.com']);

        $response = $this->getJson('/api/customers?search=Juan');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $this->assertCount(1, $data['data']);
            $this->assertEquals('Juan Pérez', $data['data'][0]['name']);
        } else {
            // Sin paginación
            $this->assertCount(1, $data);
            $this->assertEquals('Juan Pérez', $data[0]['name']);
        }
    }

    public function test_can_get_customers_with_pagination(): void
    {
        // Crear más customers de los que caben en una página
        Customer::factory()->count(15)->create();

        $response = $this->getJson('/api/customers?page=1&per_page=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(5, $data['data']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertEquals(15, $data['pagination']['total']);
    }

    public function test_can_get_customers_with_sorting(): void
    {
        // Crear customers con nombres específicos para ordenamiento
        Customer::factory()->create(['name' => 'CCC']);
        Customer::factory()->create(['name' => 'AAA']);
        Customer::factory()->create(['name' => 'BBB']);

        $response = $this->getJson('/api/customers?sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $customers = $data['data'];
        } else {
            // Sin paginación
            $customers = $data;
        }

        // Verificar que están ordenados alfabéticamente
        $names = array_column($customers, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function test_can_get_customers_with_all_filters(): void
    {
        // Crear customers para probar todos los filtros
        Customer::factory()->create(['name' => 'Juan ABC', 'email' => 'juan@abc.com']);
        Customer::factory()->create(['name' => 'María ABC', 'email' => 'maria@abc.com']);
        Customer::factory()->create(['name' => 'Carlos XYZ', 'email' => 'carlos@xyz.com']);

        $response = $this->getJson('/api/customers?search=ABC&page=1&per_page=2&sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);

        // Verificar que solo devuelve customers con "ABC" en el nombre
        foreach ($data['data'] as $customer) {
            $this->assertStringContainsString('ABC', $customer['name']);
        }
    }

    public function test_validates_sort_direction(): void
    {
        $response = $this->getJson('/api/customers?sort_direction=invalid');

        $response->assertStatus(200); // Debería funcionar con ordenamiento por defecto
    }

    public function test_limits_per_page_to_maximum(): void
    {
        // Crear muchos customers
        Customer::factory()->count(200)->create();

        $response = $this->getJson('/api/customers?per_page=150'); // Más del límite de 100

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(100, $data['pagination']['per_page']); // Debería limitarse a 100
        $this->assertEquals(200, $data['pagination']['total']);
    }

    public function test_search_by_last_name(): void
    {
        Customer::factory()->create(['last_name' => 'Pérez']);
        Customer::factory()->create(['last_name' => 'García']);
        Customer::factory()->create(['last_name' => 'Pérez López']);

        $response = $this->getJson('/api/customers?search=Pérez');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $customers = $data['data'];
        } else {
            $customers = $data;
        }

        // Verificar que devuelve customers con "Pérez" en el apellido
        foreach ($customers as $customer) {
            $this->assertStringContainsString('Pérez', $customer['last_name']);
        }
    }

    public function test_search_by_email(): void
    {
        Customer::factory()->create(['email' => 'juan@example.com']);
        Customer::factory()->create(['email' => 'maria@test.com']);
        Customer::factory()->create(['email' => 'carlos@example.org']);

        $response = $this->getJson('/api/customers?search=example');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $customers = $data['data'];
        } else {
            $customers = $data;
        }

        // Verificar que devuelve customers con "example" en el email
        foreach ($customers as $customer) {
            $this->assertStringContainsString('example', $customer['email']);
        }
    }

    public function test_search_by_dni(): void
    {
        Customer::factory()->create(['dni' => 12345678]);
        Customer::factory()->create(['dni' => 87654321]);
        Customer::factory()->create(['dni' => 12345679]);

        $response = $this->getJson('/api/customers?search=123456');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $customers = $data['data'];
        } else {
            $customers = $data;
        }

        // Verificar que devuelve customers con "123456" en el DNI
        foreach ($customers as $customer) {
            $this->assertStringContainsString('123456', (string) $customer['dni']);
        }
    }

    public function test_search_by_city(): void
    {
        Customer::factory()->create(['city' => 'Buenos Aires']);
        Customer::factory()->create(['city' => 'Córdoba']);
        Customer::factory()->create(['city' => 'Buenos Aires Norte']);

        $response = $this->getJson('/api/customers?search=Buenos Aires');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $customers = $data['data'];
        } else {
            $customers = $data;
        }

        // Verificar que devuelve customers con "Buenos Aires" en la ciudad
        foreach ($customers as $customer) {
            $this->assertStringContainsString('Buenos Aires', $customer['city']);
        }
    }
}
