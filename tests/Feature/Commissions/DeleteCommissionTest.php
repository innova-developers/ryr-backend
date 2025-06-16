<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteCommissionTest extends TestCase
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

    public function test_it_deletes_commission_successfully(): void
    {
        // Arrange
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'date' => '2024-03-21',
            'status' => CommissionStatus::DEPOSITO->value,
            'total' => '1000.00',
        ]);

        CommissionItem::factory()->create([
            'commission_id' => $commission->id,
            'type' => CommissionItemType::ORDINARIA->value,
            'size' => CommissionItemSize::SMALL->value,
            'quantity' => 1,
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
            'detail' => 'Item Test',
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/commissions/{$commission->id}");

        // Assert
        $response->assertStatus(200);
    }

    public function test_it_returns_404_when_commission_not_found(): void
    {
        $response = $this->actingAs($this->user)
            ->deleteJson('/api/commissions/999');

        $response->assertStatus(404);
    }

    public function test_it_requires_authentication(): void
    {
        $response = $this->deleteJson('/api/commissions/1');

        $response->assertStatus(401);
    }
}
