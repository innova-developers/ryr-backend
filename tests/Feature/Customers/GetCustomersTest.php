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
        $user = User::factory()->create(['role' => 'administrador', 'branch_id' => null]);
        $token = $user->createToken('test-token')->plainTextToken;
        Customer::factory()->count(3)->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/customers');

        $response->assertStatus(200);

        $data = $response->json();
        if (isset($data['data'])) {
            // Con paginación
            $this->assertGreaterThanOrEqual(3, count($data['data']));
            $this->assertArrayHasKey('pagination', $data);
        } else {
            // Sin paginación
            $this->assertGreaterThanOrEqual(3, count($data));
        }
    }
}
