<?php

namespace Tests\Feature\Expenses;

use App\Shared\Models\Expense;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Transport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'administrador']);
        $this->transport = Transport::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_get_expenses_by_transport(): void
    {
        // Arrange
        $expenses = Expense::factory()->count(3)->create(['transport_id' => $this->transport->id]);

        // Act
        $response = $this->getJson("/api/transports/{$this->transport->id}/expenses");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'transport_id',
                    'date',
                    'detail',
                    'amount',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_can_create_expense(): void
    {
        // Arrange
        $expenseData = [
            'date' => '2024-03-25',
            'detail' => 'Combustible',
            'amount' => 150.50,
        ];

        // Act
        $response = $this->postJson("/api/transports/{$this->transport->id}/expenses", $expenseData);

        // Assert
        $response->assertStatus(201)
            ->assertJson([
                'transport_id' => $this->transport->id,
                'date' => '2024-03-25',
                'detail' => 'Combustible',
                'amount' => '150.50',
            ]);

        $this->assertDatabaseHas('expenses', [
            'transport_id' => $this->transport->id,
            'detail' => 'Combustible',
            'amount' => 150.50,
        ]);
    }

    public function test_can_update_expense(): void
    {
        // Arrange
        $expense = Expense::factory()->create(['transport_id' => $this->transport->id]);
        $updateData = [
            'date' => '2024-03-26',
            'detail' => 'Mantenimiento',
            'amount' => 200.00,
        ];

        // Act
        $response = $this->putJson("/api/transports/{$this->transport->id}/expenses/{$expense->id}", $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'id' => $expense->id,
                'transport_id' => $this->transport->id,
                'date' => '2024-03-26',
                'detail' => 'Mantenimiento',
                'amount' => '200.00',
            ]);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'detail' => 'Mantenimiento',
            'amount' => 200.00,
        ]);
    }

    public function test_can_delete_expense(): void
    {
        // Arrange
        $expense = Expense::factory()->create(['transport_id' => $this->transport->id]);

        // Act
        $response = $this->deleteJson("/api/transports/{$this->transport->id}/expenses/{$expense->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson(['message' => 'Gasto eliminado correctamente']);

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_returns_404_when_expense_not_found(): void
    {
        // Act
        $response = $this->putJson("/api/transports/{$this->transport->id}/expenses/999", [
            'date' => '2024-03-25',
            'detail' => 'Test',
            'amount' => 100,
        ]);

        // Assert
        $response->assertStatus(404);
    }

    public function test_validates_required_fields_on_create(): void
    {
        // Act
        $response = $this->postJson("/api/transports/{$this->transport->id}/expenses", []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'detail', 'amount']);
    }

    public function test_validates_required_fields_on_update(): void
    {
        // Arrange
        $expense = Expense::factory()->create(['transport_id' => $this->transport->id]);

        // Act
        $response = $this->putJson("/api/transports/{$this->transport->id}/expenses/{$expense->id}", []);

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
        $response = $this->postJson("/api/transports/{$this->transport->id}/expenses", $invalidData);

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
        $response = $this->postJson("/api/transports/{$this->transport->id}/expenses", $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
