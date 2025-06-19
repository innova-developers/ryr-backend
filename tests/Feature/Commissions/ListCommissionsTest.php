<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListCommissionsTest extends TestCase
{
    use RefreshDatabase;
    private User $user;
    private Branch $branch;
    private Customer $customer;
    private Destination $destination;
    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->location = Location::factory()->create();
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
            'origin_location_id' => $this->location->id,
            'destination_location_id' => $this->location->id,
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

        CommissionLog::create([
            'commission_id' => $commission->id,
            'user_id' => $this->user->id,
            'previous_status' => "",
            'new_status' => CommissionStatus::DEPOSITO->value,
            'details' => 'Comisión creada',
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson('/api/commissions');

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'client_id',
                        'client',
                        'destination',
                        'branch_id',
                        'date',
                        'status',
                        'user_id',
                        'total',
                        'items',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
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
            'origin_location_id' => $this->location->id,
            'destination_location_id' => $this->location->id,
        ]);

        CommissionLog::create([
            'commission_id' => $commission->id,
            'user_id' => $this->user->id,
            'previous_status' => "",
            'new_status' => CommissionStatus::DEPOSITO->value,
            'details' => 'Comisión creada',
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
