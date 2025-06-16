<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
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

    public function test_can_create_commission(): void
    {
        $this->actingAs($this->user);

        $data = [
            'client_id' => $this->customer->id,
            'date' => '2024-03-21',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => CommissionStatus::DEPOSITO->value,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 2,
                    'unit_price' => 500,
                    'subtotal' => 1000,
                ],
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::LARGE->value,
                    'quantity' => 1,
                    'unit_price' => 800,
                    'subtotal' => 800,
                ],
                [
                    'type' => CommissionItemType::EXTRAORDINARIA->value,
                    'quantity' => 1,
                    'unit_price' => 1500,
                    'subtotal' => 1500,
                    'detail' => 'Manejo especial',
                ],
            ],
            'total' => 3300,
        ];

        $response = $this->postJson('/api/commissions', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('commissions', [
            'client_id' => $this->customer->id,
            'status' => CommissionStatus::DEPOSITO->value,
        ]);

        $this->assertDatabaseCount('commission_items', 3);
    }

    public function test_can_get_commission_statuses(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/commissions/statuses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'value',
                    'label',
                ],
            ]);

        $statuses = collect($response->json())->pluck('value')->toArray();
        $this->assertEquals(
            array_map(fn ($status) => $status->value, CommissionStatus::cases()),
            $statuses
        );
    }


    public function test_validates_required_fields(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/commissions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'client_id',
                'date',
                'origin',
                'destination',
                'status',
                'items',
            ]);
    }

}
