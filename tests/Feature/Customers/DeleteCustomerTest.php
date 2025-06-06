<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_customer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $customer = Customer::factory()->create();
        $response = $this->actingAs($user)
            ->deleteJson("/api/customers/{$customer->id}");
        $response->assertStatus(200);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_cannot_delete_nonexistent_customer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)
            ->deleteJson('/api/customers/999');

        $response->assertStatus(500);
    }
}
