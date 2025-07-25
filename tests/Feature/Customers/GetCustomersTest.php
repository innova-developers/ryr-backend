<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_customers_list(): void
    {
        $user = User::factory()->create(['role' => 'administrador']);
        $token = $user->createToken('test-token')->plainTextToken;
        Customer::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'dni',
                    'name',
                    'last_name',
                    'address',
                    'city',
                    'phone',
                    'is_premium',
                    'user',
                ],
            ]);
    }
}
