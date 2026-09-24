<?php

namespace Tests\Feature;

use App\Services\CustomerPortalUserService;
use App\Services\VerificationCodeService;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RC-518 (devuelta por QA el 16/09).
 *
 * "El inconveniente del portal continúa. Cliente Nicolás Rodríguez, DNI 32.756.426.
 * Ese cliente tiene cargado un correo genérico. Intenten editarlo y reemplazarlo por
 * nicolasrg27@gmail.com. [...] el sistema no permite modificar correctamente el correo."
 *
 * En producción el cliente 267 tenía cliente-267@ryrcomisiones.com (placeholder de la
 * migración) y user_id apuntando al administrador. nicolasrg27@gmail.com lo retenían
 * "test nico", un cliente de prueba borrado el 25/02/2026, y su usuario de portal
 * (user 24), que quedó vivo. Los logs de acceso lo muestran byte a byte:
 * - PUT /customers/267 → 422 (69 bytes) el 14/09 y dos veces el 15/09: la regla
 *   unique de customers.email contaba al cliente borrado ("The email has already
 *   been taken").
 * - verify-code con nicolasrg27@gmail.com → 200 (214 bytes): success con
 *   customer: null y token del usuario de prueba; el dashboard veía webCustomer "null"
 *   y rebotaba a la landing. Eso era "el portal no carga".
 */
class CustomerEmailPortalSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $branch = Branch::factory()->create();
        $this->admin = User::factory()->create([
            'name' => 'nicolas rodriguez',
            'email' => 'admin@ryrcomisiones.com',
            'role' => UserRole::ADMINISTRADOR->value,
            'branch_id' => $branch->id,
        ]);
    }

    /**
     * El escenario de producción: el cliente real con el placeholder y user_id del
     * admin, y el cliente de prueba borrado con su usuario de portal todavía vivo.
     *
     * @return array{0: Customer, 1: Customer, 2: User}
     */
    private function escenarioNicolas(): array
    {
        $usuarioDePrueba = User::factory()->create([
            'name' => 'test nico',
            'email' => 'nicolasrg27@gmail.com',
            'role' => UserRole::CLIENTE->value,
            'branch_id' => null,
            'password' => Hash::make('95765432'),
        ]);

        $clienteDePrueba = Customer::factory()->create([
            'name' => 'test nico',
            'last_name' => 'nico',
            'dni' => 95765432,
            'cuit' => null,
            'email' => 'nicolasrg27@gmail.com',
            'user_id' => $usuarioDePrueba->id,
        ]);
        $clienteDePrueba->delete();

        $nicolas = Customer::factory()->create([
            'name' => 'NICOLAS',
            'last_name' => 'RODRIGUEZ',
            'dni' => 32756426,
            'cuit' => null,
            'email' => 'cliente-267@ryrcomisiones.com',
            'phone' => '3401516937',
            'address' => 'Rivadavia 676 y chacabucco',
            'city' => 'SAN GENARO',
            'user_id' => $this->admin->id,
        ]);

        return [$nicolas, $clienteDePrueba, $usuarioDePrueba];
    }

    /**
     * Lo que manda el formulario de Clientes.jsx al editar.
     */
    private function editarEmail(Customer $customer, string $email)
    {
        return $this->actingAs($this->admin, 'sanctum')->putJson("/api/customers/{$customer->id}", [
            'type' => 'individual',
            'name' => $customer->name,
            'last_name' => $customer->last_name,
            'razon_social' => '',
            'email' => $email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'city' => $customer->city,
            'dni' => (string) $customer->dni,
            'cuit' => '',
            'auto_calculate_iva' => true,
            'iva_status' => 'auto',
            'observations' => '',
        ]);
    }

    private function codigoPara(string $identifier, string $type = 'email'): string
    {
        $service = app(VerificationCodeService::class);
        $code = $service->generateCode();
        $service->storeCode($identifier, $type, $code);

        return $code;
    }

    private function entrarConCodigo(string $identifier, string $type = 'email')
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/verify-code', [
            'identifier' => $identifier,
            'type' => $type,
            'code' => $this->codigoPara($identifier, $type),
        ]);
    }

    public function test_nicolas_rodriguez_pasa_del_email_generico_a_nicolasrg27_aunque_lo_retenga_un_cliente_borrado(): void
    {
        [$nicolas, $clienteDePrueba] = $this->escenarioNicolas();

        $this->editarEmail($nicolas, 'nicolasrg27@gmail.com')
            ->assertStatus(200)
            ->assertJsonPath('email', 'nicolasrg27@gmail.com');

        $this->assertSame('nicolasrg27@gmail.com', $nicolas->fresh()->email);

        // El cliente borrado suelta el email (el índice único lo seguía reservando) y
        // sigue borrado.
        $borrado = Customer::withTrashed()->find($clienteDePrueba->id);
        $this->assertSame("cliente-{$clienteDePrueba->id}-eliminado@ryrcomisiones.com", $borrado->email);
        $this->assertNotNull($borrado->deleted_at);
    }

    public function test_el_usuario_de_portal_huerfano_con_ese_email_queda_vinculado_a_nicolas(): void
    {
        [$nicolas, , $usuarioDePrueba] = $this->escenarioNicolas();

        $this->editarEmail($nicolas, 'nicolasrg27@gmail.com')->assertStatus(200);

        // Un solo usuario con ese email, vinculado por user_id (ya no al admin) y con el
        // nombre del cliente en vez de "test nico".
        $this->assertSame(1, User::where('email', 'nicolasrg27@gmail.com')->count());
        $this->assertSame($usuarioDePrueba->id, $nicolas->fresh()->user_id);
        $this->assertSame('NICOLAS RODRIGUEZ', $usuarioDePrueba->fresh()->name);

        // El administrador no se toca.
        $this->assertSame('admin@ryrcomisiones.com', $this->admin->fresh()->email);
        $this->assertSame(UserRole::ADMINISTRADOR, $this->admin->fresh()->role);
    }

    public function test_despues_de_corregir_el_email_nicolas_entra_al_portal_por_codigo_y_ve_su_cliente(): void
    {
        [$nicolas, , $usuarioDePrueba] = $this->escenarioNicolas();

        $this->editarEmail($nicolas, 'nicolasrg27@gmail.com')->assertStatus(200);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/validate-identifier', ['identifier' => 'nicolasrg27@gmail.com', 'type' => 'email'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer_id', $nicolas->id);

        $login = $this->entrarConCodigo('nicolasrg27@gmail.com')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('customer.id', $nicolas->id)
            ->assertJsonPath('user.id', $usuarioDePrueba->id);

        $token = $login->json('token');
        $this->assertNotEmpty($token);

        // Con el token que recibe el front, el dashboard resuelve al cliente real.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/client/profile')
            ->assertStatus(200)
            ->assertJsonPath('customer.id', $nicolas->id)
            ->assertJsonPath('customer.email', 'nicolasrg27@gmail.com');
    }

    public function test_antes_de_corregir_el_email_el_login_frena_con_un_mensaje_en_vez_de_un_token_sin_cliente(): void
    {
        [, , $usuarioDePrueba] = $this->escenarioNicolas();

        $this->postJson('/api/validate-identifier', ['identifier' => 'nicolasrg27@gmail.com', 'type' => 'email'])
            ->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_registration', false)
            ->assertJsonPath('message', 'Este email no está asociado a ningún cliente activo de R&R. Comunicate con nosotros para actualizar tus datos.');

        $this->entrarConCodigo('nicolasrg27@gmail.com')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('token');

        $this->assertSame(0, $usuarioDePrueba->tokens()->count(), 'no se emite un token que el dashboard va a rebotar');
    }

    public function test_un_email_de_otro_cliente_vivo_se_rechaza_diciendo_de_quien_es(): void
    {
        [$nicolas] = $this->escenarioNicolas();
        $otro = Customer::factory()->create([
            'name' => 'OMAR',
            'last_name' => 'PEREZ',
            'dni' => 16984391,
            'email' => 'operez@example.com',
        ]);
        $usuariosAntes = User::orderBy('id')->pluck('email', 'id')->all();

        $this->editarEmail($nicolas, 'operez@example.com')
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El email operez@example.com ya lo usa el cliente OMAR PEREZ (DNI 16984391).');

        $this->assertSame('cliente-267@ryrcomisiones.com', $nicolas->fresh()->email);
        $this->assertSame('operez@example.com', $otro->fresh()->email);
        $this->assertSame($usuariosAntes, User::orderBy('id')->pluck('email', 'id')->all());
    }

    public function test_un_email_que_es_el_acceso_al_portal_de_otro_cliente_se_rechaza(): void
    {
        [$nicolas] = $this->escenarioNicolas();

        // Cliente vivo cuyo usuario de portal tiene un email distinto al del cliente:
        // pasarle ese email a Nicolás le daría el portal de otro.
        $accesoAjeno = User::factory()->create(['email' => 'compartido@example.com', 'role' => UserRole::CLIENTE->value]);
        $otro = Customer::factory()->create([
            'name' => 'ANA',
            'last_name' => 'GOMEZ',
            'dni' => 28111222,
            'email' => 'ana.gomez@example.com',
            'user_id' => $accesoAjeno->id,
        ]);

        $this->editarEmail($nicolas, 'compartido@example.com')
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'El email compartido@example.com ya es el acceso al portal del cliente ANA GOMEZ (DNI 28111222).');

        $this->assertSame('cliente-267@ryrcomisiones.com', $nicolas->fresh()->email);
        $this->assertSame($accesoAjeno->id, $otro->fresh()->user_id);
    }

    public function test_el_usuario_de_portal_encontrado_por_email_sigue_al_cliente_migrado(): void
    {
        // Como AGROSUM (customer 107) el 11/09: cliente migrado con user_id del admin
        // y usuario de portal creado con el email viejo, sin vínculo por user_id.
        $cliente = Customer::factory()->create([
            'email' => 'cliente-107@ryrcomisiones.com',
            'user_id' => $this->admin->id,
        ]);
        $portal = User::factory()->create([
            'email' => 'cliente-107@ryrcomisiones.com',
            'role' => UserRole::CLIENTE->value,
        ]);

        $this->editarEmail($cliente, 'agrosum@example.com')->assertStatus(200);

        $this->assertSame('agrosum@example.com', $portal->fresh()->email);
        $this->assertSame($portal->id, $cliente->fresh()->user_id);
        $this->assertDatabaseMissing('users', ['email' => 'cliente-107@ryrcomisiones.com']);
    }

    public function test_un_cliente_sin_usuario_de_portal_cambia_el_email_y_al_entrar_queda_vinculado(): void
    {
        $cliente = Customer::factory()->create([
            'email' => 'cliente-500@ryrcomisiones.com',
            'dni' => 30111222,
            'cuit' => null,
            'user_id' => $this->admin->id,
        ]);

        $this->editarEmail($cliente, 'real@example.com')->assertStatus(200);
        $this->assertDatabaseMissing('users', ['email' => 'real@example.com']);

        $this->entrarConCodigo('real@example.com')
            ->assertStatus(200)
            ->assertJsonPath('customer.id', $cliente->id);

        $nuevo = User::where('email', 'real@example.com')->firstOrFail();
        $this->assertSame(UserRole::CLIENTE, $nuevo->role);
        // Antes sólo se vinculaba si user_id estaba vacío: quedaba apuntando al admin.
        $this->assertSame($nuevo->id, $cliente->fresh()->user_id);
        $this->assertTrue(Hash::check('30111222', $nuevo->password));
    }

    public function test_un_email_retenido_por_un_usuario_dado_de_baja_se_libera(): void
    {
        $portal = User::factory()->create(['email' => 'viejo@example.com', 'role' => UserRole::CLIENTE->value]);
        $cliente = Customer::factory()->create(['email' => 'viejo@example.com', 'user_id' => $portal->id]);

        $exEmpleado = User::factory()->create(['email' => 'ex.cobrador@example.com', 'role' => UserRole::COBRADOR->value]);
        $exEmpleado->delete();

        $this->editarEmail($cliente, 'ex.cobrador@example.com')->assertStatus(200);

        $this->assertSame('ex.cobrador@example.com', $portal->fresh()->email);
        $this->assertSame("usuario-{$exEmpleado->id}-eliminado@ryrcomisiones.com", User::withTrashed()->find($exEmpleado->id)->email);
    }

    public function test_el_email_de_un_empleado_no_bloquea_la_edicion_ni_toca_al_empleado(): void
    {
        // Hay un caso real: TALIA (customer 2001) comparte email con una mostradora.
        $portal = User::factory()->create(['email' => 'talia.cliente@example.com', 'role' => UserRole::CLIENTE->value]);
        $cliente = Customer::factory()->create(['email' => 'talia.cliente@example.com', 'user_id' => $portal->id, 'phone' => '3415550000']);
        $empleada = User::factory()->create(['email' => 'talia@example.com', 'role' => UserRole::MOSTRADOR->value]);

        $this->editarEmail($cliente, 'talia@example.com')->assertStatus(200);

        $this->assertSame('talia@example.com', $cliente->fresh()->email);
        $this->assertSame(UserRole::MOSTRADOR, $empleada->fresh()->role);
        $this->assertSame('talia@example.com', $empleada->fresh()->email);
        // Conserva el acceso que ya tenía, y por teléfono sigue entrando con él.
        $this->assertSame($portal->id, $cliente->fresh()->user_id);
        $this->entrarConCodigo('3415550000', 'phone')
            ->assertStatus(200)
            ->assertJsonPath('user.id', $portal->id)
            ->assertJsonPath('customer.id', $cliente->id);
    }

    public function test_si_falla_la_sincronia_del_usuario_el_cliente_no_queda_grabado_a_medias(): void
    {
        $portal = User::factory()->create(['email' => 'antes@example.com', 'role' => UserRole::CLIENTE->value]);
        $cliente = Customer::factory()->create(['email' => 'antes@example.com', 'user_id' => $portal->id]);

        $this->partialMock(CustomerPortalUserService::class, function ($mock) {
            $mock->shouldReceive('syncAfterEmailChange')->andThrow(new \RuntimeException('falla simulada'));
        });

        $this->editarEmail($cliente, 'despues@example.com')->assertStatus(500);

        $this->assertSame('antes@example.com', $cliente->fresh()->email);
        $this->assertSame('antes@example.com', $portal->fresh()->email);
    }

    public function test_editar_otros_datos_sin_cambiar_el_email_no_toca_usuarios(): void
    {
        [$nicolas, $clienteDePrueba, $usuarioDePrueba] = $this->escenarioNicolas();

        $this->actingAs($this->admin, 'sanctum')->putJson("/api/customers/{$nicolas->id}", [
            'type' => 'individual',
            'name' => 'NICOLAS',
            'last_name' => 'RODRIGUEZ',
            'email' => 'cliente-267@ryrcomisiones.com',
            'phone' => '3401999999',
            'dni' => '32756426',
        ])->assertStatus(200)->assertJsonPath('phone', '3401999999');

        $this->assertSame($this->admin->id, $nicolas->fresh()->user_id);
        $this->assertSame('test nico', $usuarioDePrueba->fresh()->name);
        $this->assertSame('nicolasrg27@gmail.com', Customer::withTrashed()->find($clienteDePrueba->id)->email);
    }
}
