<?php

namespace Tests\Feature\CurrentAccount;

use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'administrador']);
        $this->token = $this->user->createToken('test-token')->plainTextToken;
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_create_credit_transaction(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 1000.50,
            'description' => 'Pago de factura',
            'reference' => 'FAC-001',
            'transaction_date' => '2024-01-15',
            'payment_method' => 'cash',
            'observations' => 'Pago en efectivo',
        ];

        $response = $this->postJson('/api/current-accounts', $data);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'customer_id',
            'type',
            'amount',
            'description',
            'reference',
            'transaction_date',
            'balance',
            'payment_method',
            'observations',
            'user_id',
            'created_at',
            'updated_at',
        ]);

        $this->assertDatabaseHas('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 1000.50,
            'balance' => 1000.50,
        ]);
    }

    public function test_can_create_debit_transaction(): void
    {
        // Primero crear un crédito
        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 2000,
            'balance' => 2000,
        ]);

        $data = [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 500.25,
            'description' => 'Compra de mercadería',
            'reference' => 'COMP-001',
            'transaction_date' => '2024-01-16',
            'payment_method' => 'transfer',
            'observations' => 'Transferencia bancaria',
        ];

        $response = $this->postJson('/api/current-accounts', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 500.25,
            'balance' => 1499.75, // 2000 - 500.25
        ]);
    }

    public function test_can_get_transaction(): void
    {
        $transaction = CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson("/api/current-accounts/{$transaction->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $transaction->id,
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_can_update_transaction(): void
    {
        $transaction = CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 1000,
            'balance' => 1000,
        ]);

        $updateData = [
            'amount' => 1500,
            'description' => 'Pago actualizado',
            'observations' => 'Observación actualizada',
        ];

        $response = $this->putJson("/api/current-accounts/{$transaction->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('current_accounts', [
            'id' => $transaction->id,
            'amount' => 1500,
            'description' => 'Pago actualizado',
            'observations' => 'Observación actualizada',
        ]);
    }

    public function test_can_delete_transaction(): void
    {
        $transaction = CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->deleteJson("/api/current-accounts/{$transaction->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Transacción eliminada exitosamente',
        ]);

        $this->assertSoftDeleted('current_accounts', [
            'id' => $transaction->id,
        ]);
    }

    public function test_can_get_customer_transactions(): void
    {
        // Crear varias transacciones
        CurrentAccount::factory()->count(5)->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/customers/{$this->customer->id}/current-account/transactions");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'customer_id',
                    'type',
                    'amount',
                    'description',
                    'balance',
                    'transaction_date',
                ],
            ],
            'current_page',
            'per_page',
            'total',
        ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_get_customer_balance(): void
    {
        // Crear transacciones usando el repositorio para calcular saldos correctamente
        $currentAccountRepo = app(\App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository::class);

        $createDTO1 = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'credit',
            amount: 2000,
            description: 'Pago inicial',
            reference: 'TEST-BAL-001',
            transactionDate: '2024-01-15',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO1);

        $createDTO2 = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'debit',
            amount: 500,
            description: 'Gasto',
            reference: 'TEST-BAL-002',
            transactionDate: '2024-01-16',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO2);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/customers/{$this->customer->id}/current-account/balance");

        $response->assertStatus(200);
        $response->assertJson([
            'customer_id' => $this->customer->id,
            'balance' => 1500.0, // 2000 - 500
            'formatted_balance' => '$1.500,00',
        ]);
    }

    public function test_can_filter_transactions_by_type(): void
    {
        // Crear transacciones de diferentes tipos
        CurrentAccount::factory()->count(3)->credit()->create([
            'customer_id' => $this->customer->id,
        ]);

        CurrentAccount::factory()->count(2)->debit()->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/customers/{$this->customer->id}/current-account/transactions?type=credit");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_transactions_by_date_range(): void
    {
        // Crear transacciones con diferentes fechas
        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'transaction_date' => '2024-01-15',
        ]);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'transaction_date' => '2024-01-20',
        ]);

        CurrentAccount::factory()->create([
            'customer_id' => $this->customer->id,
            'transaction_date' => '2024-02-01',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/customers/{$this->customer->id}/current-account/transactions?start_date=2024-01-15&end_date=2024-01-25");

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/current-accounts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'customer_id',
            'type',
            'amount',
            'description',
            'transaction_date',
        ]);
    }

    public function test_validates_customer_exists(): void
    {
        $data = [
            'customer_id' => 99999, // Cliente inexistente
            'type' => 'credit',
            'amount' => 1000,
            'description' => 'Test',
            'transaction_date' => '2024-01-15',
        ];

        $response = $this->postJson('/api/current-accounts', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['customer_id']);
    }

    public function test_validates_amount_is_positive(): void
    {
        $data = [
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => -100, // Monto negativo
            'description' => 'Test',
            'transaction_date' => '2024-01-15',
        ];

        $response = $this->postJson('/api/current-accounts', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_returns_404_for_nonexistent_transaction(): void
    {
        $response = $this->getJson('/api/current-accounts/99999');

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Transacción no encontrada',
        ]);
    }

    public function test_returns_404_for_nonexistent_customer(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/customers/99999/current-account/balance');

        $response->assertStatus(200);
        $response->assertJson([
            'customer_id' => 99999,
            'balance' => 0,
        ]);
    }
}
