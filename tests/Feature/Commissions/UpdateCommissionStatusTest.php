<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCommissionStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Customer $customer;
    private Destination $destination;
    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->destination = Destination::factory()->create();
        $this->branch = Branch::factory()->create();
    }

    public function test_it_updates_commission_status_successfully(): void
    {
        // Arrange
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'status' => CommissionStatus::DEPOSITO->value
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->patchJson("/api/commissions/{$commission->id}/status", [
                'status' => CommissionStatus::ENTREGAR_Y_RETIRAR->value
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson(['message' => 'Estado de la comisión actualizado correctamente']);

        $this->assertDatabaseHas('commissions', [
            'id' => $commission->id,
            'status' => CommissionStatus::ENTREGAR_Y_RETIRAR->value
        ]);
    }

    public function test_it_returns_404_when_commission_not_found(): void
    {
        // Act
        $response = $this->actingAs($this->user)
            ->patchJson('/api/commissions/999/status', [
                'status' => CommissionStatus::LAS_ROSAS->value
            ]);

        // Assert
        $response->assertStatus(404);
    }

    public function test_it_requires_authentication(): void
    {
        // Act
        $response = $this->patchJson('/api/commissions/1/status', [
            'status' => CommissionStatus::LAS_ROSAS->value
        ]);

        // Assert
        $response->assertStatus(401);
    }

    public function test_it_validates_status_enum(): void
    {
        // Arrange
        $commission = Commission::factory()->create([
            'client_id' => $this->customer->id,
            'destination_id' => $this->destination->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->patchJson("/api/commissions/{$commission->id}/status", [
                'status' => 'INVALID_STATUS'
            ]);


        // Assert
        $response->assertStatus(500);
    }
}
