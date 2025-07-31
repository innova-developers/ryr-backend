<?php

namespace Tests\Feature;

use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidateIdentifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_identifier_with_email_existing_customer()
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/validate-identifier', [
            'identifier' => 'test@example.com',
            'type' => 'email',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'exists' => true,
                'identifier' => 'test@example.com',
                'type' => 'email',
                'customer_id' => $customer->id,
            ]);
    }

    public function test_validate_identifier_with_phone_existing_customer()
    {
        $customer = Customer::factory()->create([
            'phone' => '291738293',
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/validate-identifier', [
            'identifier' => '291738293',
            'type' => 'phone',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'exists' => true,
                'identifier' => '291738293',
                'type' => 'phone',
                'customer_id' => $customer->id,
            ]);
    }

    public function test_validate_identifier_with_phone_finds_user_by_email()
    {
        $customer = Customer::factory()->create([
            'phone' => '291738293',
            'email' => 'test@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'role' => 'cliente',
        ]);

        $response = $this->postJson('/api/validate-identifier', [
            'identifier' => '291738293',
            'type' => 'phone',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'exists' => true,
                'identifier' => '291738293',
                'type' => 'phone',
                'customer_id' => $customer->id,
                'user_id' => $user->id, // Debe encontrar el usuario por email
            ]);
    }

    public function test_validate_identifier_with_non_existing_customer()
    {
        $response = $this->postJson('/api/validate-identifier', [
            'identifier' => 'nonexistent@example.com',
            'type' => 'email',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'exists' => false,
                'requires_registration' => true,
            ]);
    }

    public function test_validate_identifier_with_invalid_type()
    {
        $response = $this->postJson('/api/validate-identifier', [
            'identifier' => 'test@example.com',
            'type' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_validate_identifier_without_identifier()
    {
        $response = $this->postJson('/api/validate-identifier', [
            'type' => 'email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_verify_code_with_valid_code()
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'role' => 'cliente',
        ]);

        // Simular que se envió un código (en un test real, esto se haría con mocks)
        $code = '123456';
        cache()->put('verification_code_email_test@example.com', $code, now()->addMinutes(10));

        $response = $this->postJson('/api/verify-code', [
            'identifier' => 'test@example.com',
            'type' => 'email',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                ],
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ])
            ->assertJsonStructure([
                'token',
            ]);
    }

    public function test_verify_code_with_invalid_code()
    {
        $response = $this->postJson('/api/verify-code', [
            'identifier' => 'test@example.com',
            'type' => 'email',
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Código inválido o expirado.',
            ]);
    }

    public function test_verify_code_with_invalid_payload()
    {
        $response = $this->postJson('/api/verify-code', [
            'identifier' => 'test@example.com',
            'type' => 'email',
            'code' => '123', // Código muy corto
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_verify_code_with_phone_finds_user_and_returns_token()
    {
        $customer = Customer::factory()->create([
            'phone' => '291738293',
            'email' => 'test@example.com',
        ]);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'role' => 'cliente',
        ]);

        // Simular que se envió un código (en un test real, esto se haría con mocks)
        $code = '123456';
        cache()->put('verification_code_phone_291738293', $code, now()->addMinutes(10));

        $response = $this->postJson('/api/verify-code', [
            'identifier' => '291738293',
            'type' => 'phone',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'customer' => [
                    'id' => $customer->id,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                ],
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ])
            ->assertJsonStructure([
                'token',
            ]);

        // Verificar que el token no sea null
        $responseData = $response->json();
        $this->assertNotNull($responseData['token']);
    }
}
