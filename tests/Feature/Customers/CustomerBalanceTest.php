<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_customer_list_includes_balance_field(): void
    {
        // Crear algunas transacciones para el cliente usando el repositorio para calcular saldos correctamente
        $currentAccountRepo = app(\App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository::class);

        // Crear transacciones usando el repositorio para que se calculen los saldos automáticamente
        $createDTO = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'credit',
            amount: 1000,
            description: 'Pago inicial',
            reference: 'TEST-001',
            transactionDate: '2024-01-15',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO);

        $createDTO2 = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'debit',
            amount: 300,
            description: 'Gasto',
            reference: 'TEST-002',
            transactionDate: '2024-01-16',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO2);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'dni',
                'name',
                'email',
                'last_name',
                'address',
                'city',
                'phone',
                'is_premium',
                'user',
                'branch',
                'balance',
                'created_at',
            ],
        ]);

        // Verificar que el balance es correcto (1000 - 300 = 700)
        $customerData = collect($response->json())->firstWhere('id', $this->customer->id);
        $this->assertEquals(700, $customerData['balance']);
    }

    public function test_customer_search_includes_balance_field(): void
    {
        // Crear algunas transacciones para el cliente usando el repositorio
        $currentAccountRepo = app(\App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository::class);

        $createDTO = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'credit',
            amount: 500,
            description: 'Pago',
            reference: 'TEST-003',
            transactionDate: '2024-01-15',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO);

        $response = $this->getJson('/api/customers/search?q=' . $this->customer->name);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'dni',
                'name',
                'email',
                'last_name',
                'address',
                'city',
                'phone',
                'is_premium',
                'user',
                'branch',
                'balance',
                'created_at',
            ],
        ]);

        // Verificar que el balance es correcto
        $customerData = collect($response->json())->firstWhere('id', $this->customer->id);
        $this->assertEquals(500, $customerData['balance']);
    }

    public function test_customer_without_transactions_has_zero_balance(): void
    {
        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);

        $customerData = collect($response->json())->firstWhere('id', $this->customer->id);
        $this->assertEquals(0, $customerData['balance']);
    }

    public function test_multiple_customers_have_correct_balances(): void
    {
        $customer2 = Customer::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Crear transacciones usando el repositorio para calcular saldos correctamente
        $currentAccountRepo = app(\App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository::class);

        // Transacciones para el primer cliente
        $createDTO1 = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'credit',
            amount: 1000,
            description: 'Pago cliente 1',
            reference: 'TEST-004',
            transactionDate: '2024-01-15',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO1);

        $createDTO2 = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $this->customer->id,
            type: 'debit',
            amount: 200,
            description: 'Gasto cliente 1',
            reference: 'TEST-005',
            transactionDate: '2024-01-16',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO2);

        // Transacciones para el segundo cliente
        $createDTO3 = new \App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO(
            customerId: $customer2->id,
            type: 'credit',
            amount: 500,
            description: 'Pago cliente 2',
            reference: 'TEST-006',
            transactionDate: '2024-01-15',
            paymentMethod: 'cash',
            observations: 'Test',
            userId: $this->user->id,
        );
        $currentAccountRepo->create($createDTO3);

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);

        $customers = collect($response->json());

        $customer1Data = $customers->firstWhere('id', $this->customer->id);
        $customer2Data = $customers->firstWhere('id', $customer2->id);

        $this->assertEquals(800, $customer1Data['balance']); // 1000 - 200
        $this->assertEquals(500, $customer2Data['balance']);
    }
}
