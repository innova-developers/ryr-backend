<?php

namespace Tests\Feature;

use App\Shared\Enums\UserRole;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentAccountCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuario cliente
        $this->clientUser = User::factory()->create([
            'role' => UserRole::CLIENTE,
            'email' => 'cliente@test.com',
        ]);

        // Crear cliente
        $this->customer = Customer::factory()->create([
            'email' => 'cliente@test.com',
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'mobile' => '2915662430',
        ]);

        // Generar token
        $this->token = $this->clientUser->createToken('test-token')->plainTextToken;
    }

    public function test_account_balance_calculation_with_credits_and_debits()
    {
        // Crear transacciones de prueba
        CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 10000.00,
            'description' => 'Pago inicial',
            'balance' => 10000.00,
            'transaction_date' => now()->subDays(3),
        ]);

        CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 3000.00,
            'description' => 'Pago de comisión',
            'balance' => 7000.00,
            'transaction_date' => now()->subDays(2),
        ]);

        CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 5000.00,
            'description' => 'Nuevo pago',
            'balance' => 12000.00,
            'transaction_date' => now()->subDays(1),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/account-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'balance' => 12000.00,
                'currency' => 'ARS',
            ]);
    }

    public function test_transactions_show_correct_types_and_balances()
    {
        // Crear transacciones de prueba
        CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'credit',
            'amount' => 10000.00,
            'description' => 'Pago inicial',
            'balance' => 10000.00,
            'transaction_date' => now()->subDays(2),
        ]);

        CurrentAccount::create([
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 3000.00,
            'description' => 'Pago de comisión',
            'balance' => 7000.00,
            'transaction_date' => now()->subDays(1),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/current-account/transactions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'transactions' => [
                    '*' => [
                        'id',
                        'type',
                        'description',
                        'amount',
                        'balance',
                        'transaction_date',
                        'created_at',
                    ],
                ],
            ]);

        $transactions = $response->json('transactions');

        // Verificar que hay 2 transacciones
        $this->assertCount(2, $transactions);

        // Verificar que la primera transacción es la más reciente (debit)
        $this->assertEquals('debit', $transactions[0]['type']);
        $this->assertEquals(3000.00, $transactions[0]['amount']);
        $this->assertEquals(7000.00, $transactions[0]['balance']);

        // Verificar que la segunda transacción es la más antigua (credit)
        $this->assertEquals('credit', $transactions[1]['type']);
        $this->assertEquals(10000.00, $transactions[1]['amount']);
        $this->assertEquals(10000.00, $transactions[1]['balance']);
    }

    public function test_empty_account_returns_zero_balance()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/account-balance');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'balance' => 0,
                'currency' => 'ARS',
            ]);
    }

    public function test_empty_account_returns_empty_transactions()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ])->getJson('/api/client/current-account/transactions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'transactions' => [],
            ]);
    }
}
