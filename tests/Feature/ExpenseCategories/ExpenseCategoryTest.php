<?php

namespace Tests\Feature\ExpenseCategories;

use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_get_all_expense_categories(): void
    {
        ExpenseCategory::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/expense-categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_get_active_expense_categories_only(): void
    {
        ExpenseCategory::factory()->count(2)->create(['is_active' => true]);
        ExpenseCategory::factory()->count(2)->create(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/expense-categories?active=true');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_get_single_expense_category(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                    'created_at',
                    'updated_at',
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ]
            ]);
    }

    public function test_returns_404_for_non_existent_category(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/expense-categories/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Categoría no encontrada',
            ]);
    }

    public function test_can_create_expense_category(): void
    {
        $categoryData = [
            'name' => 'Nueva Categoría',
            'description' => 'Descripción de la nueva categoría',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/expense-categories', $categoryData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
                'message'
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Nueva Categoría',
                    'description' => 'Descripción de la nueva categoría',
                    'is_active' => true,
                ],
                'message' => 'Categoría creada exitosamente',
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Nueva Categoría',
            'description' => 'Descripción de la nueva categoría',
            'is_active' => true,
        ]);
    }

    public function test_cannot_create_category_with_duplicate_name(): void
    {
        ExpenseCategory::factory()->create(['name' => 'Categoría Existente']);

        $categoryData = [
            'name' => 'Categoría Existente',
            'description' => 'Descripción',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/expense-categories', $categoryData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_expense_category(): void
    {
        $category = ExpenseCategory::factory()->create();

        $updateData = [
            'name' => 'Categoría Actualizada',
            'description' => 'Descripción actualizada',
            'is_active' => false,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/expense-categories/{$category->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
                'message'
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Categoría Actualizada',
                    'description' => 'Descripción actualizada',
                    'is_active' => false,
                ],
                'message' => 'Categoría actualizada exitosamente',
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Categoría Actualizada',
            'description' => 'Descripción actualizada',
            'is_active' => false,
        ]);
    }

    public function test_cannot_update_to_duplicate_name(): void
    {
        ExpenseCategory::factory()->create(['name' => 'Categoría 1']);
        $category2 = ExpenseCategory::factory()->create(['name' => 'Categoría 2']);

        $updateData = [
            'name' => 'Categoría 1',
            'description' => 'Descripción',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/expense-categories/{$category2->id}", $updateData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_delete_expense_category(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente',
            ]);

        $this->assertSoftDeleted('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_returns_404_when_deleting_non_existent_category(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/expense-categories/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Categoría no encontrada',
            ]);
    }
} 