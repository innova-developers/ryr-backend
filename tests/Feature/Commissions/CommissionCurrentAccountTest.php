<?php

namespace Tests\Feature\Commissions;

use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionCurrentAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Destination $destination;
    private Branch $branch;
    private Location $originLocation;
    private Location $destinationLocation;
    private CurrentAccountRepository $currentAccountRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'administrador']);
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->branch = Branch::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();
        $this->currentAccountRepository = app(CurrentAccountRepository::class);
    }

    public function test_can_create_commission_with_a_cuenta_true(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'subtotal' => 1000,
                ],
            ],
            'total' => 1000,
            'a_cuenta' => true,
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        // Verificar que se creó la comisión
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::DEPOSITO->value,
            'total' => 1000,
        ]);

        // Verificar que se creó la transacción en cuenta corriente
        $this->assertDatabaseHas('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 1000,
            'description' => "Comisión #1 - {$this->destination->origin} a {$this->destination->destination}",
            'reference' => 'COM-1',
        ]);

        // Verificar que el saldo del cliente se actualizó correctamente
        $balance = $this->currentAccountRepository->getCustomerBalance($this->customer->id);
        $this->assertEquals(-1000, $balance);
    }

    public function test_can_create_commission_with_a_cuenta_false(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'subtotal' => 1000,
                ],
            ],
            'total' => 1000,
            'a_cuenta' => false,
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        // Verificar que se creó la comisión
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::DEPOSITO->value,
            'total' => 1000,
        ]);

        // Verificar que NO se creó transacción en cuenta corriente
        $this->assertDatabaseMissing('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 1000,
        ]);

        // Verificar que el saldo del cliente no cambió
        $balance = $this->currentAccountRepository->getCustomerBalance($this->customer->id);
        $this->assertEquals(0, $balance);
    }

    public function test_can_create_commission_without_a_cuenta_parameter(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'subtotal' => 1000,
                ],
            ],
            'total' => 1000,
            // No incluir a_cuenta
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        // Verificar que se creó la comisión
        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::DEPOSITO->value,
            'total' => 1000,
        ]);

        // Verificar que NO se creó transacción en cuenta corriente
        $this->assertDatabaseMissing('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 1000,
        ]);

        // Verificar que el saldo del cliente no cambió
        $balance = $this->currentAccountRepository->getCustomerBalance($this->customer->id);
        $this->assertEquals(0, $balance);
    }

    public function test_multiple_commissions_a_cuenta_accumulate_balance(): void
    {
        $this->actingAs($this->user);

        // Crear primera comisión a cuenta
        $data1 = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'subtotal' => 500,
                ],
            ],
            'total' => 500,
            'a_cuenta' => true,
        ];

        $response1 = $this->postJson('/api/commissions', $data1);
        $response1->assertStatus(201);

        // Crear segunda comisión a cuenta
        $data2 = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-22',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::LARGE->value,
                    'quantity' => 1,
                    'unit_price' => 800,
                    'subtotal' => 800,
                ],
            ],
            'total' => 800,
            'a_cuenta' => true,
        ];

        $response2 = $this->postJson('/api/commissions', $data2);
        $response2->assertStatus(201);

        // Verificar que se crearon ambas transacciones
        $this->assertDatabaseHas('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('current_accounts', [
            'customer_id' => $this->customer->id,
            'type' => 'debit',
            'amount' => 800,
        ]);

        // Verificar que el saldo total es la suma de ambas deudas
        $balance = $this->currentAccountRepository->getCustomerBalance($this->customer->id);
        $this->assertEquals(-1300, $balance); // -500 - 800
    }

    public function test_validates_a_cuenta_boolean(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 1,
                    'unit_price' => 500,
                    'subtotal' => 500,
                ],
            ],
            'total' => 500,
            'a_cuenta' => 'invalid_value', // Valor inválido
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['a_cuenta']);
    }
}
