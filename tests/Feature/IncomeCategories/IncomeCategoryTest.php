<?php

namespace Tests\Feature\IncomeCategories;

use App\Shared\Models\IncomeCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_income_categories(): void
    {
        $categories = IncomeCategory::factory()->count(3)->create();

        $response = $this->getJson('/api/income-categories');

        $response->assertStatus(200);
        $response->assertJsonCount(3);
    }

    public function test_can_create_income_category(): void
    {
        $response = $this->postJson('/api/income-categories', [
            'name' => 'Nueva Categoría',
            'description' => 'Descripción de la nueva categoría',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Categoría de ingreso creada exitosamente',
        ]);

        $this->assertDatabaseHas('income_categories', [
            'name' => 'Nueva Categoría',
            'description' => 'Descripción de la nueva categoría',
        ]);
    }

    public function test_can_create_income_category_without_description(): void
    {
        $response = $this->postJson('/api/income-categories', [
            'name' => 'Categoría Sin Descripción',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Categoría de ingreso creada exitosamente',
        ]);

        $this->assertDatabaseHas('income_categories', [
            'name' => 'Categoría Sin Descripción',
            'description' => null,
        ]);
    }

    public function test_cannot_create_income_category_with_duplicate_name(): void
    {
        IncomeCategory::factory()->create(['name' => 'Categoría Existente']);

        $response = $this->postJson('/api/income-categories', [
            'name' => 'Categoría Existente',
            'description' => 'Descripción',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_can_get_income_category_by_id(): void
    {
        $category = IncomeCategory::factory()->create();

        $response = $this->getJson("/api/income-categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $category->id,
            'name' => $category->name,
        ]);
    }

    public function test_returns_404_when_income_category_not_found(): void
    {
        $response = $this->getJson('/api/income-categories/999');

        $response->assertStatus(404);
    }

    public function test_can_update_income_category(): void
    {
        $category = IncomeCategory::factory()->create();

        $response = $this->putJson("/api/income-categories/{$category->id}", [
            'name' => 'Categoría Actualizada',
            'description' => 'Descripción actualizada',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Categoría de ingreso actualizada exitosamente',
        ]);

        $this->assertDatabaseHas('income_categories', [
            'id' => $category->id,
            'name' => 'Categoría Actualizada',
            'description' => 'Descripción actualizada',
        ]);
    }

    public function test_cannot_update_income_category_with_duplicate_name(): void
    {
        $category1 = IncomeCategory::factory()->create(['name' => 'Categoría 1']);
        $category2 = IncomeCategory::factory()->create(['name' => 'Categoría 2']);

        $response = $this->putJson("/api/income-categories/{$category2->id}", [
            'name' => 'Categoría 1',
            'description' => 'Descripción',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_can_delete_income_category(): void
    {
        $category = IncomeCategory::factory()->create();

        $response = $this->deleteJson("/api/income-categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Categoría de ingreso eliminada exitosamente',
        ]);

        $this->assertDatabaseMissing('income_categories', ['id' => $category->id]);
    }

    public function test_returns_404_when_deleting_nonexistent_income_category(): void
    {
        $response = $this->deleteJson('/api/income-categories/999');

        $response->assertStatus(404);
    }

    public function test_validation_requires_name(): void
    {
        $response = $this->postJson('/api/income-categories', [
            'description' => 'Solo descripción',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_validation_name_max_length(): void
    {
        $response = $this->postJson('/api/income-categories', [
            'name' => str_repeat('a', 256), // Más de 255 caracteres
            'description' => 'Descripción',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_validation_description_max_length(): void
    {
        $response = $this->postJson('/api/income-categories', [
            'name' => 'Categoría',
            'description' => str_repeat('a', 1001), // Más de 1000 caracteres
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }
}
