<?php

namespace Tests\Feature\Customers;

use App\Shared\Enums\UserRole;
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

    public function test_updates_user_email_when_customer_email_changes(): void
    {
        $branch = Branch::factory()->create();
        $adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => $branch->id]);

        // Crear un usuario cliente
        $clientUser = User::factory()->create([
            'email' => 'cliente@example.com',
            'role' => UserRole::CLIENTE,
            'branch_id' => null,
        ]);

        // Crear un cliente asociado al usuario
        $customer = Customer::factory()->create([
            'email' => 'cliente@example.com',
            'user_id' => $clientUser->id,
        ]);

        // Actualizar el email del cliente
        $newEmail = 'nuevo-email@example.com';
        $response = $this->actingAs($adminUser)
            ->putJson("/api/customers/{$customer->id}", [
                'dni' => $customer->dni,
                'name' => $customer->name,
                'last_name' => $customer->last_name,
                'mobile' => $customer->mobile,
                'email' => $newEmail,
                'address' => $customer->address,
                'city' => $customer->city,
                'phone' => $customer->phone,
                'is_premium' => $customer->is_premium,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'email' => $newEmail,
            ]);

        // Verificar que el email se actualizó en la tabla customers
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => $newEmail,
        ]);

        // Verificar que el email también se actualizó en la tabla users
        $this->assertDatabaseHas('users', [
            'id' => $clientUser->id,
            'email' => $newEmail,
            'role' => UserRole::CLIENTE->value,
        ]);

        // Verificar que el email anterior ya no existe en users
        $this->assertDatabaseMissing('users', [
            'id' => $clientUser->id,
            'email' => 'cliente@example.com',
        ]);
    }

    public function test_does_not_update_user_email_when_customer_has_no_associated_user(): void
    {
        $branch = Branch::factory()->create();
        $adminUser = User::factory()->create(['role' => UserRole::ADMINISTRADOR, 'branch_id' => $branch->id]);

        // Crear un cliente sin usuario asociado
        $customer = Customer::factory()->create([
            'email' => 'cliente@example.com',
            'user_id' => null,
        ]);

        // Actualizar el email del cliente
        $newEmail = 'nuevo-email@example.com';
        $response = $this->actingAs($adminUser)
            ->putJson("/api/customers/{$customer->id}", [
                'dni' => $customer->dni,
                'name' => $customer->name,
                'last_name' => $customer->last_name,
                'mobile' => $customer->mobile,
                'email' => $newEmail,
                'address' => $customer->address,
                'city' => $customer->city,
                'phone' => $customer->phone,
                'is_premium' => $customer->is_premium,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'email' => $newEmail,
            ]);

        // Verificar que el email se actualizó en la tabla customers
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'email' => $newEmail,
        ]);

        // No debería existir ningún usuario con este email
        $this->assertDatabaseMissing('users', [
            'email' => $newEmail,
        ]);
    }
}
