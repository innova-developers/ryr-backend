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
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id]);
        $customer = Customer::factory()->create([
            'dni' => 12345678,
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'is_premium' => false,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/customers/{$customer->id}", [
                'dni' => 26338400,
                'name' => 'Jane',
                'last_name' => 'Smith',
                'mobile' => '1234567890',
                'email' => 'jane@example.com',
                'address' => '456 Oak St',
                'city' => 'Los Angeles',
                'phone' => '0987654321',
                'maps_url' => 'https://maps.google.com',
                'business_hours' => '8-17',
                'observations' => 'Updated customer',
                'is_premium' => true,
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
    }

    public function test_cannot_update_customer_with_duplicate_email(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id]);
        $existingCustomer = Customer::factory()->create([
            'email' => "existing@test.com",
        ]);
        $customer = Customer::factory()->create([
            'email' => "original@test.com",
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/customers/{$customer->id}", [
                'dni' => 12345678,
                'name' => 'John',
                'last_name' => 'Doe',
                'email' => "existing@test.com",
                'is_premium' => true,
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_update_nonexistent_customer(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id]);

        $response = $this->actingAs($user)
            ->putJson('/api/customers/999', [
                'dni' => 12345678,
                'name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'is_premium' => true,
            ]);

        $response->assertStatus(404);
    }
}
