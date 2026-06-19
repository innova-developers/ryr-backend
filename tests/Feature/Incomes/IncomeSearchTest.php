<?php

namespace Tests\Feature\Incomes;

use App\Shared\Models\Income;
use App\Shared\Models\IncomeCategory;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomeSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private IncomeCategory $category1;
    private IncomeCategory $category2;

    protected function setUp(): void
    {
        parent::setUp();

        // Super-admin autenticado (branch_id null = ve todo); /api/incomes requiere auth
        Sanctum::actingAs(User::factory()->create(['role' => 'administrador', 'branch_id' => null]));

        $this->user = User::factory()->create(['name' => 'Juan Pérez']);
        $this->category1 = IncomeCategory::factory()->create(['name' => 'Ventas']);
        $this->category2 = IncomeCategory::factory()->create(['name' => 'Comisiones']);
    }

    public function test_can_search_incomes_by_detail(): void
    {
        // Crear ingresos con diferentes detalles
        Income::factory()->create([
            'detail' => 'TEST_Venta de productos',
            'amount' => 1000,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        Income::factory()->create([
            'detail' => 'TEST_Pago de servicios',
            'amount' => 500,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        // Buscar por "TEST_venta"
        $response = $this->getJson('/api/incomes?search=TEST_venta');

        $response->assertStatus(200);
        $incomes = $response->json('data');

        $this->assertCount(1, $incomes);
        $this->assertEquals('TEST_Venta de productos', $incomes[0]['detail']);
    }

    public function test_can_search_incomes_by_amount(): void
    {
        // Crear ingresos con diferentes montos
        Income::factory()->create([
            'detail' => 'TEST_Ingreso 1',
            'amount' => 1500,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        Income::factory()->create([
            'detail' => 'TEST_Ingreso 2',
            'amount' => 2500,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        // Buscar por "1500"
        $response = $this->getJson('/api/incomes?search=1500');

        $response->assertStatus(200);
        $incomes = $response->json('data');

        $this->assertCount(1, $incomes);
        $this->assertEquals(1500, $incomes[0]['amount']);
    }

    public function test_can_search_incomes_by_category_name(): void
    {
        // Crear categorías de prueba
        $testCategory1 = IncomeCategory::factory()->create(['name' => 'TEST_Ventas']);
        $testCategory2 = IncomeCategory::factory()->create(['name' => 'TEST_Comisiones']);

        // Crear ingresos con diferentes categorías
        Income::factory()->create([
            'detail' => 'TEST_Ingreso 1',
            'amount' => 1000,
            'user_id' => $this->user->id,
            'income_category_id' => $testCategory1->id, // TEST_Ventas
        ]);

        Income::factory()->create([
            'detail' => 'TEST_Ingreso 2',
            'amount' => 2000,
            'user_id' => $this->user->id,
            'income_category_id' => $testCategory2->id, // TEST_Comisiones
        ]);

        // Buscar por "TEST_ventas"
        $response = $this->getJson('/api/incomes?search=TEST_ventas');

        $response->assertStatus(200);
        $incomes = $response->json('data');

        $this->assertCount(1, $incomes);
        $this->assertEquals('TEST_Ventas', $incomes[0]['category']['name']);
    }

    public function test_can_search_incomes_by_user_name(): void
    {
        $user2 = User::factory()->create(['name' => 'TEST_Mariana Gomez']);

        // Crear ingresos con diferentes usuarios
        Income::factory()->create([
            'detail' => 'TEST_Ingreso 1',
            'amount' => 1000,
            'user_id' => $this->user->id, // Juan Pérez
            'income_category_id' => $this->category1->id,
        ]);

        Income::factory()->create([
            'detail' => 'TEST_Ingreso 2',
            'amount' => 2000,
            'user_id' => $user2->id, // TEST_María García
            'income_category_id' => $this->category1->id,
        ]);

        // Buscar por nombre de usuario (sin acentos: el LIKE de sqlite en tests no es
        // case-insensitive con acentos; en MySQL prod con utf8_ci sí)
        $response = $this->getJson('/api/incomes?search=Mariana');

        $response->assertStatus(200);
        $incomes = $response->json('data');

        $this->assertCount(1, $incomes);
        $this->assertEquals('TEST_Mariana Gomez', $incomes[0]['user']['name']);
    }

    public function test_search_is_case_insensitive(): void
    {
        Income::factory()->create([
            'detail' => 'TEST_Venta de productos',
            'amount' => 1000,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        // Buscar con mayúsculas
        $response = $this->getJson('/api/incomes?search=TEST_VENTA');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));

        // Buscar con minúsculas
        $response = $this->getJson('/api/incomes?search=test_venta');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_search_returns_empty_when_no_matches(): void
    {
        Income::factory()->create([
            'detail' => 'TEST_Venta de productos',
            'amount' => 1000,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        // Buscar algo que no existe
        $response = $this->getJson('/api/incomes?search=TEST_inexistente');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_search_works_with_other_filters(): void
    {
        $dateFrom = '2025-09-01';
        $dateTo = '2025-09-30';

        // Crear ingresos en diferentes fechas
        Income::factory()->create([
            'detail' => 'Venta de productos',
            'amount' => 1000,
            'date' => '2025-09-15',
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        Income::factory()->create([
            'detail' => 'Pago de servicios',
            'amount' => 2000,
            'date' => '2025-10-15', // Fuera del rango
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        // Buscar con filtros de fecha y búsqueda
        $response = $this->getJson("/api/incomes?dateFrom={$dateFrom}&dateTo={$dateTo}&search=venta");

        $response->assertStatus(200);
        $incomes = $response->json('data');

        $this->assertCount(1, $incomes);
        $this->assertEquals('Venta de productos', $incomes[0]['detail']);
    }

    public function test_search_with_partial_matches(): void
    {
        Income::factory()->create([
            'detail' => 'Venta de productos electrónicos',
            'amount' => 1000,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        Income::factory()->create([
            'detail' => 'Venta de ropa',
            'amount' => 500,
            'user_id' => $this->user->id,
            'income_category_id' => $this->category1->id,
        ]);

        Income::factory()->create([
            'detail' => 'Pago de servicios',
            'amount' => 2000,
            'user_id' => $this->user->id,
            // Categoría distinta a "Ventas" para que NO matchee la búsqueda por nombre de categoría
            'income_category_id' => $this->category2->id,
        ]);

        // Buscar por "venta" - debería encontrar ambos ingresos de venta
        $response = $this->getJson('/api/incomes?search=venta');

        $response->assertStatus(200);
        $incomes = $response->json('data');

        $this->assertCount(2, $incomes);
        $this->assertTrue(
            collect($incomes)->every(fn ($income) => str_contains(strtolower($income['detail']), 'venta'))
        );
    }
}
