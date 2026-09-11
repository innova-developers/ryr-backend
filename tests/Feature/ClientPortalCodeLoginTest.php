<?php

namespace Tests\Feature;

use App\Services\VerificationCodeService;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RC-518 (PORTAL CLIENTES).
 *
 * "No funciona el inicio de sesión suponiendo que el código es correcto. El envío se
 * hace pero no carga."
 *
 * El código llegaba y se validaba bien; lo que fallaba era el final: verifyCode
 * respondía success: true con token: null cuando el cliente no tenía usuario de
 * portal, así que el front se quedaba sin nada con qué entrar. En la copia de
 * producción del 11/09 sólo 520 de los 3.451 clientes con email tenían usuario —el
 * resto llegó con la migración del sistema viejo, que creó el cliente y no el usuario.
 */
class ClientPortalCodeLoginTest extends TestCase
{
    use RefreshDatabase;

    private function pedirCodigo(string $identifier, string $type = 'email'): string
    {
        $service = app(VerificationCodeService::class);
        $code = $service->generateCode();
        $service->storeCode($identifier, $type, $code);

        return $code;
    }

    public function test_un_cliente_sin_usuario_de_portal_entra_igual(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'migrado@example.com',
            'dni' => 30111222,
            'cuit' => null,
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'migrado@example.com']);

        $code = $this->pedirCodigo($customer->email);

        $response = $this->postJson('/api/verify-code', [
            'identifier' => $customer->email,
            'type' => 'email',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('token'), 'sin token el portal no puede iniciar sesión');

        $this->assertDatabaseHas('users', [
            'email' => 'migrado@example.com',
            'role' => UserRole::CLIENTE->value,
        ]);
    }

    public function test_el_usuario_creado_queda_vinculado_al_cliente(): void
    {
        $customer = Customer::factory()->create(['email' => 'vinculo@example.com', 'user_id' => null]);

        $code = $this->pedirCodigo($customer->email);

        $this->postJson('/api/verify-code', [
            'identifier' => $customer->email,
            'type' => 'email',
            'code' => $code,
        ])->assertStatus(200);

        $user = User::where('email', 'vinculo@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame($user->id, Customer::find($customer->id)->user_id);
    }

    public function test_un_cliente_que_ya_tiene_usuario_no_genera_otro(): void
    {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create(['email' => 'existente@example.com']);
        User::factory()->create([
            'email' => 'existente@example.com',
            'role' => UserRole::CLIENTE->value,
            'password' => Hash::make('secreto123'),
            'branch_id' => $branch->id,
        ]);

        $code = $this->pedirCodigo($customer->email);

        $this->postJson('/api/verify-code', [
            'identifier' => $customer->email,
            'type' => 'email',
            'code' => $code,
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSame(1, User::where('email', 'existente@example.com')->count());
    }

    public function test_un_codigo_incorrecto_sigue_sin_dar_acceso(): void
    {
        $customer = Customer::factory()->create(['email' => 'intruso@example.com']);

        $this->pedirCodigo($customer->email);

        $this->postJson('/api/verify-code', [
            'identifier' => $customer->email,
            'type' => 'email',
            'code' => '000000',
        ])->assertStatus(400)->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
    }
}
