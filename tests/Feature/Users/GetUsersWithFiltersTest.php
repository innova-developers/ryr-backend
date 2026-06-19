<?php

namespace Tests\Feature\Users;

use App\Shared\Enums\UserRole;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetUsersWithFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario administrador
        $this->adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => null]);
        Sanctum::actingAs($this->adminUser);
    }

    public function test_can_get_users_without_filters(): void
    {
        // Crear algunos usuarios
        User::factory()->count(3)->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $this->assertIsArray($response->json());
        $this->assertGreaterThan(0, count($response->json()));
    }

    public function test_can_get_users_with_search_filter(): void
    {
        // Crear usuarios con nombres específicos
        User::factory()->create(['name' => 'Juan Pérez', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'María García', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'Carlos López', 'role' => UserRole::ADMINISTRADOR]);

        $response = $this->getJson('/api/users?search=Juan');

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

    public function test_can_get_users_with_pagination(): void
    {
        // Crear más usuarios de los que caben en una página
        User::factory()->count(15)->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->getJson('/api/users?page=1&per_page=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(5, $data['data']);
        $this->assertEquals(1, $data['pagination']['current_page']);
        $this->assertEquals(5, $data['pagination']['per_page']);
        $this->assertEquals(16, $data['pagination']['total']); // 15 + 1 del setUp
    }

    public function test_can_get_users_with_sorting(): void
    {
        // Crear usuarios con nombres específicos para ordenamiento
        User::factory()->create(['name' => 'Carlos', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'Ana', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'Beatriz', 'role' => UserRole::ADMINISTRADOR]);

        $response = $this->getJson('/api/users?sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            // Con paginación
            $users = $data['data'];
        } else {
            // Sin paginación
            $users = $data;
        }

        // Verificar que están ordenados alfabéticamente
        $names = array_column($users, 'name');
        $sortedNames = $names;
        sort($sortedNames);
        $this->assertEquals($sortedNames, $names);
    }

    public function test_can_get_users_with_all_filters(): void
    {
        // Crear usuarios para probar todos los filtros
        User::factory()->create(['name' => 'Juan Pérez', 'email' => 'juan@test.com', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'Juan García', 'email' => 'juan2@test.com', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'María López', 'email' => 'maria@test.com', 'role' => UserRole::ADMINISTRADOR]);

        $response = $this->getJson('/api/users?search=Juan&page=1&per_page=2&sort_by=name&sort_direction=asc');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(2, $data['data']);

        // Verificar que solo devuelve usuarios con "Juan" en el nombre
        foreach ($data['data'] as $user) {
            $this->assertStringContainsString('Juan', $user['name']);
        }
    }

    public function test_excludes_cliente_users(): void
    {
        // Crear usuarios con diferentes roles
        User::factory()->create(['name' => 'Admin User', 'role' => UserRole::ADMINISTRADOR]);
        User::factory()->create(['name' => 'Cliente User', 'role' => UserRole::CLIENTE]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $data = $response->json();

        if (isset($data['data'])) {
            $users = $data['data'];
        } else {
            $users = $data;
        }

        // Verificar que no hay usuarios con rol 'cliente'
        foreach ($users as $user) {
            $this->assertNotEquals('cliente', $user['role']);
        }
    }

    public function test_validates_sort_direction(): void
    {
        $response = $this->getJson('/api/users?sort_direction=invalid');

        $response->assertStatus(200); // Debería funcionar con ordenamiento por defecto
    }

    public function test_limits_per_page_to_maximum(): void
    {
        // Crear muchos usuarios
        User::factory()->count(200)->create(['role' => UserRole::ADMINISTRADOR]);

        $response = $this->getJson('/api/users?per_page=150'); // Más del límite de 100

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('pagination', $data);
        $this->assertEquals(100, $data['pagination']['per_page']); // Debería limitarse a 100
        $this->assertEquals(201, $data['pagination']['total']); // 200 + 1 del setUp
    }
}
