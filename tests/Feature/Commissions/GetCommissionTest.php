<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionItemSize;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private Customer $customer;
    private Destination $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
    }

    public function test_it_returns_commission_data_when_found(): void
    {
        // Arrange
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'date' => '2024-03-21',
            'status' => CommissionStatus::DEPOSITO->value,
            'total' => '1000.00'
        ]);

        CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'type' => CommissionItemType::ORDINARIA->value,
            'size' => CommissionItemSize::SMALL->value,
            'quantity' => 1,
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
            'detail' => 'Item Test'
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/commissions/{$commission->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'client_id',
                    'client' => [
                        'id',
                        'name'
                    ],
                    'destination_id',
                    'destination' => [
                        'id',
                        'name'
                    ],
                    'branch_id',
                    'branch' => [
                        'id',
                        'name'
                    ],
                    'date',
                    'status',
                    'user_id',
                    'user' => [
                        'id',
                        'name'
                    ],
                    'total',
                    'items' => [
                        '*' => [
                            'id',
                            'type',
                            'size',
                            'quantity',
                            'unit_price',
                            'subtotal',
                            'detail',
                            'created_at',
                            'updated_at'
                        ]
                    ],
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'data' => [
                    'id' => $commission->id,
                    'client_id' => $this->customer->id,
                    'client' => [
                        'id' => $this->customer->id,
                        'name' => $this->customer->name
                    ],
                    'destination_id' => $this->destination->id,
                    'destination' => [
                        'id' => $this->destination->id,
                        'name' => $this->destination->name
                    ],
                    'branch_id' => $this->branch->id,
                    'branch' => [
                        'id' => $this->branch->id,
                        'name' => $this->branch->name
                    ],
                    'date' => $commission->date->format('Y-m-d H:i:s'),
                    'status' => CommissionStatus::DEPOSITO->value,
                    'user_id' => $this->user->id,
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name
                    ],
                    'total' => '1000.00'
                ]
            ]);
    }

    public function test_it_returns_404_when_commission_not_found(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/commissions/999');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Comisión no encontrada'
            ]);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->getJson('/api/commissions/1');

        $response->assertStatus(401);
    }
}
