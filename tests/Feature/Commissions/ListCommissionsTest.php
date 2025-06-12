<?php

namespace Tests\Feature\Commissions;

use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionItemSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCommissionsTest extends TestCase
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

    public function test_it_returns_commissions_with_items(): void
    {
        // Arrange
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'status' => CommissionStatus::DEPOSITO->value,
        ]);

        CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'type' => CommissionItemType::ORDINARIA->value,
            'size' => CommissionItemSize::SMALL->value,
            'quantity' => 2,
            'unit_price' => 500.00,
            'subtotal' => 1000.00,
            'detail' => 'Manejo especial',
        ]);

        CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'type' => CommissionItemType::EXTRAORDINARIA->value,
            'size' => CommissionItemSize::LARGE->value,
            'quantity' => 1,
            'unit_price' => 800.00,
            'subtotal' => 800.00,
            'detail' => null,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/commissions');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'id' => $commission->id,
                        'client_id' => $this->customer->id,
                        'destination_id' => $this->destination->id,
                        'branch_id' => $this->branch->id,
                        'date' => $commission->date->toJSON(),
                        'status' => CommissionStatus::DEPOSITO->value,
                        'user_id' => $this->user->id,
                        'total' => number_format($commission->total, 2, '.', ''),
                        'created_at' => $commission->created_at->toJSON(),
                        'updated_at' => $commission->updated_at->toJSON(),
                        'items' => [
                            [
                                'id' => 1,
                                'commission_id' => $commission->id,
                                'type' => CommissionItemType::ORDINARIA->value,
                                'size' => CommissionItemSize::SMALL->value,
                                'quantity' => 2,
                                'unit_price' => number_format(500.00, 2, '.', ''),
                                'subtotal' => number_format(1000.00, 2, '.', ''),
                                'detail' => 'Manejo especial',
                                'created_at' => $commission->created_at->toJSON(),
                                'updated_at' => $commission->updated_at->toJSON(),
                            ],
                            [
                                'id' => 2,
                                'commission_id' => $commission->id,
                                'type' => CommissionItemType::EXTRAORDINARIA->value,
                                'size' => CommissionItemSize::LARGE->value,
                                'quantity' => 1,
                                'unit_price' => number_format(800.00, 2, '.', ''),
                                'subtotal' => number_format(800.00, 2, '.', ''),
                                'detail' => null,
                                'created_at' => $commission->created_at->toJSON(),
                                'updated_at' => $commission->updated_at->toJSON(),
                            ],
                        ],
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 1,
                ],
            ]);
    }

    public function test_it_filters_commissions_by_status(): void
    {
        // Arrange
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'status' => CommissionStatus::DEPOSITO->value,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/commissions?status=' . CommissionStatus::DEPOSITO->value);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'id' => $commission->id,
                        'status' => CommissionStatus::DEPOSITO->value,
                    ],
                ],
                'meta' => [
                    'total' => 1,
                ],
            ]);
    }

    public function test_it_returns_empty_array_when_no_commissions(): void
    {
        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/commissions');

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                ],
            ]);
    }

    public function test_it_requires_authentication(): void
    {
        // Act
        $response = $this->getJson('/api/commissions');

        // Assert
        $response->assertStatus(401);
    }
}
