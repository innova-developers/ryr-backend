<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_customer(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id]);

        $response = $this->actingAs($user)
            ->postJson('/api/customers', [
                'dni' => 12345678,
                'name' => 'John',
                'last_name' => 'Doe',
                'mobile' => '1234567890',
                'email' => 'john@example.com',
                'address' => '123 Main St',
                'city' => 'New York',
                'phone' => '0987654321',
                'maps_url' => 'https://maps.google.com',
                'business_hours' => '9-18',
                'observations' => 'Test customer',
                'is_premium' => true,
            ]);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'dni',
                'name',
                'last_name',
                'mobile',
                'email',
                'address',
                'city',
                'phone',
                'maps_url',
                'business_hours',
                'observations',
                'is_premium',
                'user_id',
                'created_at',
                'updated_at',
            ]);

        $this->assertDatabaseHas('customers', [
            'dni' => 12345678,
            'name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'is_premium' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John',
            'email' => 'john@example.com',
            'role' => 'cliente',
            'branch_id' => $branch->id,
        ]);
    }

    public function test_cannot_create_customer_without_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'administrador']);

        $response = $this->actingAs($user)
            ->postJson('/api/customers', []);

        $response->assertStatus(422);
    }

    public function test_cannot_create_customer_with_duplicate_email(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id]);
        $existingCustomer = Customer::factory()->create([
            'email' => "example@test.com",
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/customers', [
                'dni' => 12345678,
                'name' => 'John',
                'last_name' => 'Doe',
                'email' => "example@test.com",
                'is_premium' => false,
            ]);

        $response->assertStatus(422);
    }
}
