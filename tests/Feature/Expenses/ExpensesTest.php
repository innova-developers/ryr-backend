<?php

namespace Tests\Feature\Expenses;

use App\Shared\Models\Expense;
use App\Shared\Models\ExpenseCategory;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Transport $transport;
    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
        $this->transport = Transport::factory()->create();
        $this->category = ExpenseCategory::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    // Tests para gastos de transportes (compatibilidad)
    public function test_can_get_expenses_by_transport(): void
    {
        // Arrange
        $expenses = Expense::factory()->count(3)->create(['transport_id' => $this->transport->id]);

        // Act
        $response = $this->getJson("/api/transports/{$this->transport->id}/expenses");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'transport_id',
                    'expense_category_id',
                    'user_id',
                    'date',
                    'detail',
                    'amount',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertCount(3, $response->json());
    }

    public function test_can_create_expense_for_transport(): void
    {
        // Arrange
        $expenseData = [
            'date' => '2024-03-25',
            'detail' => 'Combustible',
            'amount' => 150.50,
            'expense_category_id' => $this->category->id,
        ];

        // Act
        $response = $this->postJson("/api/transports/{$this->transport->id}/expenses", $expenseData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'transport_id',
                    'expense_category_id',
                    'date',
                    'detail',
                    'amount',
                    'created_at',
                    'updated_at',
                    'transport',
                    'category',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'transport_id' => $this->transport->id,
                    'expense_category_id' => $this->category->id,
                    'date' => '2024-03-25',
                    'detail' => 'Combustible',
                    'amount' => '150.50',
                ],
                'message' => 'Gasto creado exitosamente',
            ]);

        $this->assertDatabaseHas('expenses', [
            'transport_id' => $this->transport->id,
            'expense_category_id' => $this->category->id,
            'detail' => 'Combustible',
            'amount' => 150.50,
        ]);
    }

    public function test_can_update_expense_for_transport(): void
    {
        // Arrange
        $expense = Expense::factory()->create(['transport_id' => $this->transport->id]);
        $updateData = [
            'date' => '2024-03-26',
            'detail' => 'Mantenimiento',
            'amount' => 200.00,
            'expense_category_id' => $this->category->id,
        ];

        // Act
        $response = $this->putJson("/api/transports/{$this->transport->id}/expenses/{$expense->id}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'transport_id',
                    'expense_category_id',
                    'date',
                    'detail',
                    'amount',
                    'created_at',
                    'updated_at',
                    'transport',
                    'category',
                ],
                'message',
            ])
            ->assertJson([
                'data' => [
                    'id' => $expense->id,
                    'transport_id' => $this->transport->id,
                    'expense_category_id' => $this->category->id,
                    'date' => '2024-03-26',
                    'detail' => 'Mantenimiento',
                    'amount' => '200.00',
                ],
                'message' => 'Gasto actualizado exitosamente',
            ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'transport_id' => $this->transport->id,
            'expense_category_id' => $this->category->id,
            'detail' => 'Mantenimiento',
            'amount' => 200.00,
        ]);
    }

    public function test_can_delete_expense_for_transport(): void
    {
        // Arrange
        $expense = Expense::factory()->create(['transport_id' => $this->transport->id]);

        // Act
        $response = $this->deleteJson("/api/transports/{$this->transport->id}/expenses/{$expense->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gasto eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    // Tests para gastos generales
    public function test_can_get_all_expenses(): void
    {
        // Arrange
        $expenses = Expense::factory()->count(3)->create();

        // Act
        $response = $this->getJson('/api/expenses');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'transport_id',
                        'expense_category_id',
                        'user_id',
                        'date',
                        'detail',
                        'amount',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'total',
                'per_page',
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_get_expenses_by_transport_id_parameter(): void
    {
        // Arrange
        $expenses = Expense::factory()->count(3)->create(['transport_id' => $this->transport->id]);
        Expense::factory()->count(2)->withoutTransport()->create();

        // Act
        $response = $this->getJson("/api/expenses?transport_id={$this->transport->id}");

        // Assert
        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    public function test_can_create_general_expense(): void
    {
        // Arrange
        $expenseData = [
            'date' => '2024-03-25',
            'detail' => 'Gasto de oficina',
            'amount' => 100.00,
            'expense_category_id' => $this->category->id,
        ];

        // Act
        $response = $this->postJson('/api/expenses', $expenseData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'transport_id',
                'expense_category_id',
                'user_id',
                'date',
                'detail',
                'amount',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('expenses', [
            'transport_id' => null,
            'expense_category_id' => $this->category->id,
            'detail' => 'Gasto de oficina',
            'amount' => 100.00,
        ]);
    }

    public function test_can_create_expense_with_transport_and_category(): void
    {
        // Arrange
        $expenseData = [
            'transport_id' => $this->transport->id,
            'expense_category_id' => $this->category->id,
            'date' => '2024-03-25',
            'detail' => 'Combustible para transporte',
            'amount' => 150.50,
        ];

        // Act
        $response = $this->postJson('/api/expenses', $expenseData);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'transport_id' => $this->transport->id,
                'expense_category_id' => $this->category->id,
                'detail' => 'Combustible para transporte',
                'amount' => '150.50',
            ]);

        $this->assertDatabaseHas('expenses', [
            'transport_id' => $this->transport->id,
            'expense_category_id' => $this->category->id,
            'detail' => 'Combustible para transporte',
            'amount' => 150.50,
        ]);
    }

    public function test_can_update_general_expense(): void
    {
        // Arrange
        $expense = Expense::factory()->withoutTransport()->create();
        $updateData = [
            'date' => '2024-03-26',
            'detail' => 'Gasto actualizado',
            'amount' => 200.00,
            'expense_category_id' => $this->category->id,
        ];

        // Act
        $response = $this->putJson("/api/expenses/{$expense->id}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $expense->id,
                    'transport_id' => null,
                    'expense_category_id' => $this->category->id,
                    'detail' => 'Gasto actualizado',
                    'amount' => '200.00',
                ],
                'message' => 'Gasto actualizado exitosamente',
            ]);
    }

    public function test_can_delete_general_expense(): void
    {
        // Arrange
        $expense = Expense::factory()->create();

        // Act
        $response = $this->deleteJson("/api/expenses/{$expense->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gasto eliminado exitosamente',
            ]);

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_returns_404_when_expense_not_found(): void
    {
        // Act
        $response = $this->putJson("/api/expenses/999", [
            'date' => '2024-03-25',
            'detail' => 'Test',
            'amount' => 100,
        ]);

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Gasto no encontrado',
            ]);
    }

    public function test_validates_required_fields_on_create(): void
    {
        // Act
        $response = $this->postJson('/api/expenses', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'detail', 'amount']);
    }

    public function test_validates_required_fields_on_update(): void
    {
        // Arrange
        $expense = Expense::factory()->create();

        // Act
        $response = $this->putJson("/api/expenses/{$expense->id}", []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'detail', 'amount']);
    }

    public function test_validates_amount_is_numeric(): void
    {
        // Arrange
        $invalidData = [
            'date' => '2024-03-25',
            'detail' => 'Test',
            'amount' => 'not-a-number',
        ];

        // Act
        $response = $this->postJson('/api/expenses', $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_validates_amount_is_positive(): void
    {
        // Arrange
        $invalidData = [
            'date' => '2024-03-25',
            'detail' => 'Test',
            'amount' => -10,
        ];

        // Act
        $response = $this->postJson('/api/expenses', $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_validates_transport_id_exists(): void
    {
        // Arrange
        $invalidData = [
            'transport_id' => 999,
            'date' => '2024-03-25',
            'detail' => 'Test',
            'amount' => 100,
        ];

        // Act
        $response = $this->postJson('/api/expenses', $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['transport_id']);
    }

    public function test_validates_expense_category_id_exists(): void
    {
        // Arrange
        $invalidData = [
            'expense_category_id' => 999,
            'date' => '2024-03-25',
            'detail' => 'Test',
            'amount' => 100,
        ];

        // Act
        $response = $this->postJson('/api/expenses', $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expense_category_id']);
    }
}
