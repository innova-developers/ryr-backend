<?php

namespace Tests\Feature\Incomes;

use App\Shared\Models\Income;
use App\Shared\Models\IncomeCategory;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Super-admin (branch_id null) autenticado: rutas /api/incomes requieren auth:sanctum + adminOrCadete
        $admin = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
        Sanctum::actingAs($admin);
    }

    public function test_can_get_all_incomes(): void
    {
        $category = IncomeCategory::factory()->create();
        $user = User::factory()->create();
        $incomes = Income::factory()->count(3)->create([
            'income_category_id' => $category->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/incomes');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_can_create_income_with_user(): void
    {
        $category = IncomeCategory::factory()->create();
        $user = User::factory()->create();

        $response = $this->postJson('/api/incomes', [
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-01-15',
            'detail' => 'Ingreso por ventas',
            'amount' => 1500.50,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('incomes', [
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-01-15 00:00:00',
            'detail' => 'Ingreso por ventas',
            'amount' => 1500.50,
        ]);
    }

    public function test_can_create_income_without_user(): void
    {
        $category = IncomeCategory::factory()->create();

        $response = $this->postJson('/api/incomes', [
            'income_category_id' => $category->id,
            'date' => '2024-01-15',
            'detail' => 'Ingreso sin usuario',
            'amount' => 1000.00,
        ]);

        $response->assertStatus(201);
        // El controlador devuelve directamente el modelo, no un wrapper success/message

        $this->assertDatabaseHas('incomes', [
            'income_category_id' => $category->id,
            'user_id' => null,
            'date' => '2024-01-15 00:00:00',
            'detail' => 'Ingreso sin usuario',
            'amount' => 1000.00,
        ]);
    }

    public function test_can_update_income(): void
    {
        $category = IncomeCategory::factory()->create();
        $user = User::factory()->create();
        $income = Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user->id,
        ]);

        $response = $this->putJson("/api/incomes/{$income->id}", [
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-01-20',
            'detail' => 'Ingreso actualizado',
            'amount' => 2000.00,
        ]);

        $response->assertStatus(201);
        // El controlador devuelve directamente el modelo, no un wrapper success/message

        $this->assertDatabaseHas('incomes', [
            'id' => $income->id,
            'detail' => 'Ingreso actualizado',
            'amount' => 2000.00,
        ]);
    }

    public function test_can_delete_income(): void
    {
        $category = IncomeCategory::factory()->create();
        $user = User::factory()->create();
        $income = Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson("/api/incomes/{$income->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Ingreso eliminado exitosamente',
        ]);

        $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    }

    public function test_returns_404_when_income_not_found(): void
    {
        $category = IncomeCategory::factory()->create();
        $user = User::factory()->create();

        $response = $this->putJson('/api/incomes/999', [
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-01-15',
            'detail' => 'Test',
            'amount' => 100,
        ]);

        $response->assertStatus(404);
    }

    public function test_can_filter_incomes_by_date_range(): void
    {
        $category = IncomeCategory::factory()->create();
        $user = User::factory()->create();

        // Crear ingresos con fechas específicas
        Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-01-15',
        ]);
        Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-01-20',
        ]);
        Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user->id,
            'date' => '2024-02-01',
        ]);

        $response = $this->getJson('/api/incomes?dateFrom=2024-01-15&dateTo=2024-01-25');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_can_filter_incomes_by_category(): void
    {
        $category1 = IncomeCategory::factory()->create();
        $category2 = IncomeCategory::factory()->create();
        $user = User::factory()->create();

        Income::factory()->create([
            'income_category_id' => $category1->id,
            'user_id' => $user->id,
        ]);
        Income::factory()->create([
            'income_category_id' => $category2->id,
            'user_id' => $user->id,
        ]);

        $response = $this->getJson("/api/incomes?income_category_id={$category1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_filter_incomes_by_user(): void
    {
        $category = IncomeCategory::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user1->id,
        ]);
        Income::factory()->create([
            'income_category_id' => $category->id,
            'user_id' => $user2->id,
        ]);

        $response = $this->getJson("/api/incomes?user_id={$user1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_get_incomes_by_user_route(): void
    {
        $category = IncomeCategory::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Ingresos del usuario 1
        Income::factory()->count(2)->create([
            'income_category_id' => $category->id,
            'user_id' => $user1->id,
        ]);

        // Ingresos del usuario 2
        Income::factory()->count(3)->create([
            'income_category_id' => $category->id,
            'user_id' => $user2->id,
        ]);

        $response = $this->getJson("/api/users/{$user1->id}/incomes");

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_returns_empty_array_when_user_has_no_incomes(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}/incomes");

        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');
    }
}
