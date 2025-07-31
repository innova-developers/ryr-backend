<?php

namespace Tests\Feature;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_customer_publicly()
    {
        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com',
            'phone' => '2914716316',
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Cliente y usuario creados correctamente',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'customer' => [
                    'id',
                    'name',
                    'last_name',
                    'email',
                    'mobile',
                    'phone',
                    'address',
                    'city',
                    'dni',
                ],
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ]);

        // Verificar que el cliente se creó en la base de datos
        $this->assertDatabaseHas('customers', [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com',
            'mobile' => '2914716316',
            'phone' => '2914716316',
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
        ]);

        // Verificar que el usuario se creó en la base de datos
        $this->assertDatabaseHas('users', [
            'name' => 'Juancito',
            'email' => 'sepujuan96@gmail.com',
            'role' => 'cliente',
        ]);

        // Verificar que el cliente tiene el user_id correcto
        $customer = Customer::where('email', 'sepujuan96@gmail.com')->first();
        $user = \App\Shared\Models\User::where('email', 'sepujuan96@gmail.com')->first();
        $this->assertEquals($user->id, $customer->user_id);
    }

    public function test_validates_required_fields()
    {
        $response = $this->postJson('/api/customers/public', []);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_validates_email_format()
    {
        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'invalid-email',
            'phone' => '2914716316',
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'email',
                ],
            ]);
    }

    public function test_validates_duplicate_email()
    {
        // Crear un cliente existente
        Customer::factory()->create([
            'email' => 'sepujuan96@gmail.com',
            'dni' => '12345678',
        ]);

        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com', // Email duplicado
            'phone' => '2914716316',
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'email',
                ],
            ]);
    }

    public function test_validates_duplicate_dni()
    {
        // Crear un cliente existente
        Customer::factory()->create([
            'email' => 'otro@email.com',
            'dni' => '41332783',
        ]);

        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com',
            'phone' => '2914716316',
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783', // DNI duplicado
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Datos inválidos',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => [
                    'dni',
                ],
            ]);
    }

    public function test_uses_phone_as_mobile_when_mobile_not_provided()
    {
        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com',
            'phone' => '2914716316', // Solo phone, sin mobile
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(201);

        // Verificar que se usó phone como mobile
        $this->assertDatabaseHas('customers', [
            'email' => 'sepujuan96@gmail.com',
            'mobile' => '2914716316',
            'phone' => '2914716316',
        ]);
    }

    public function test_uses_mobile_when_both_provided()
    {
        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com',
            'phone' => '2914716316',
            'mobile' => '2914716317', // Mobile diferente
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(201);

        // Verificar que se usó mobile en lugar de phone
        $this->assertDatabaseHas('customers', [
            'email' => 'sepujuan96@gmail.com',
            'mobile' => '2914716317',
            'phone' => '2914716316',
        ]);
    }

    public function test_creates_customer_with_optional_fields()
    {
        $payload = [
            'name' => 'Juancito',
            'last_name' => 'Sepu',
            'email' => 'sepujuan96@gmail.com',
            'phone' => '2914716316',
            'address' => 'Puerto Madryn 623',
            'city' => 'Monte Hermoso',
            'dni' => '41332783',
            'maps_url' => 'https://maps.google.com',
            'business_hours' => 'Lunes a Viernes 9-18',
            'observations' => 'Cliente preferencial',
            'is_premium' => true,
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(201);

        // Verificar que se guardaron los campos opcionales
        $this->assertDatabaseHas('customers', [
            'email' => 'sepujuan96@gmail.com',
            'maps_url' => 'https://maps.google.com',
            'business_hours' => 'Lunes a Viernes 9-18',
            'observations' => 'Cliente preferencial',
            'is_premium' => true,
        ]);
    }

    public function test_creates_user_with_temporary_password()
    {
        $payload = [
            'name' => 'Test User',
            'last_name' => 'Test',
            'email' => 'testpassword@example.com',
            'phone' => '2914716319',
            'address' => 'Test Address',
            'city' => 'Test City',
            'dni' => '22222222',
        ];

        $response = $this->postJson('/api/customers/public', $payload);

        $response->assertStatus(201);

        // Verificar que el usuario se creó correctamente
        $user = \App\Shared\Models\User::where('email', 'testpassword@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserRole::CLIENTE, $user->role);
        $this->assertNotNull($user->password); // Verificar que tiene password
    }
}
