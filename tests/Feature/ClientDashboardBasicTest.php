<?php

namespace Tests\Feature;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientDashboardBasicTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_model_has_correct_fields()
    {
        $customer = Customer::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juan@test.com',
            'mobile' => '2915662430',
        ]);

        $this->assertEquals('Juan', $customer->name);
        $this->assertEquals('Pérez', $customer->last_name);
        $this->assertEquals('juan@test.com', $customer->email);
        $this->assertEquals('2915662430', $customer->mobile);
    }

    public function test_user_model_has_client_role()
    {
        $user = User::factory()->create([
            'role' => UserRole::CLIENTE,
            'email' => 'cliente@test.com',
        ]);

        $this->assertEquals(UserRole::CLIENTE, $user->role);
        $this->assertEquals('cliente@test.com', $user->email);
    }

    public function test_customer_can_be_found_by_email()
    {
        $customer = Customer::factory()->create([
            'email' => 'cliente@test.com',
        ]);

        $foundCustomer = Customer::where('email', 'cliente@test.com')->first();

        $this->assertNotNull($foundCustomer);
        $this->assertEquals($customer->id, $foundCustomer->id);
    }

    public function test_customer_can_be_found_by_mobile()
    {
        $customer = Customer::factory()->create([
            'mobile' => '2915662430',
        ]);

        $foundCustomer = Customer::where('mobile', '2915662430')->first();

        $this->assertNotNull($foundCustomer);
        $this->assertEquals($customer->id, $foundCustomer->id);
    }
}
