<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_customer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/customers/{$customer->id}", [
                'dni' => 26338400,
                'name' => 'Jane',
                'last_name' => 'Smith',
                'mobile' => '9876543210',
                'email' => 'jane@example.com',
                'address' => '456 Oak St',
                'city' => 'Los Angeles',
                'phone' => '1234567890',
                'maps_url' => 'https://maps.google.com/updated',
                'business_hours' => '10-19',
                'observations' => 'Updated customer',
                'is_premium' => true,
                'branch_id' => $branch->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'dni' => 26338400,
                'name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'is_premium' => true,
            ]);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'dni' => 26338400,
            'name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'is_premium' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => $user->name,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
        ]);
    }

    public function test_cannot_update_customer_with_duplicate_email(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create();
        Customer::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/customers/{$customer->id}", [
                'dni' => 18765431,
                'name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'existing@example.com',
                'is_premium' => true,
                'branch_id' => $branch->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_update_nonexistent_customer(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $branch = Branch::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/customers/999', [
                'dni' => 18765431,
                'name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
                'is_premium' => true,
                'branch_id' => $branch->id,
            ]);

        $response->assertStatus(404);
    }
}
