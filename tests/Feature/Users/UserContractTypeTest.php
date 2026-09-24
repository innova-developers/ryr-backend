<?php

namespace Tests\Feature\Users;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * RC-532.
 *
 * "Edición de usuario: no guarda cuando el tipo de contratación no es Sueldo fijo."
 * Captura del 22/09: modal de Empleados con el usuario "test" (testeando1@test.com,
 * Mostrador, sucursal TEST) pasando a "Fijo + Comisiones", y abajo "No se pudo editar
 * el usuario".
 *
 * La migración 2026_05_11_124500 dejó la columna contract_type con fixed_salary,
 * per_pickup y fixed_plus_commission, pero EditUserRequest y CreateUserRequest seguían
 * validando 'in:fixed_salary,commission_based': todo lo que no fuera Sueldo Fijo volvía
 * 422 "The selected contract type is invalid." y el front lo tapaba con el mensaje
 * genérico. En los access logs de producción hay 12 PUT /api/users con 422 entre el
 * 14 y el 22/09 (usuarios 3, 17 y 145). Además payment_per_pickup no llegaba ni al DTO
 * ni al listado, así que "Por Retiro" no podía guardarse aunque pasara la validación.
 */
class UserContractTypeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchTest;

    private User $usuarioTest;

    protected function setUp(): void
    {
        parent::setUp();

        // Sin sucursal, como el superadmin: el listado filtra por la sucursal del que mira.
        $admin = User::factory()->create(['role' => UserRole::ADMINISTRADOR->value, 'branch_id' => null]);
        $this->actingAs($admin, 'sanctum');

        $this->branchTest = Branch::factory()->create(['name' => 'TEST']);

        // Estado real del usuario 145 en la copia de producción del 23/09.
        $this->usuarioTest = User::factory()->create([
            'name' => 'test',
            'email' => 'testeando1@test.com',
            'password' => Hash::make('clave-original'),
            'role' => UserRole::MOSTRADOR->value,
            'branch_id' => $this->branchTest->id,
            'contract_type' => 'fixed_salary',
            'base_salary' => 600000,
            'income_percentage' => null,
            'commission_percentage' => null,
            'payment_per_pickup' => null,
        ]);
    }

    /**
     * Arma el body igual que handleEdit() en Usuarios.jsx: branch_id como string del
     * select, sin password cuando se deja en blanco y en null los montos que no aplican
     * al tipo elegido.
     */
    private function payloadDelModal(array $contratacion): array
    {
        return array_merge([
            'name' => 'test',
            'email' => 'testeando1@test.com',
            'role' => UserRole::MOSTRADOR->value,
            'branch_id' => (string) $this->branchTest->id,
            'base_salary' => null,
            'commission_percentage' => null,
            'payment_per_pickup' => null,
        ], $contratacion);
    }

    public function test_el_usuario_test_de_la_captura_pasa_a_fijo_mas_comisiones(): void
    {
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => 600000,
            'commission_percentage' => 25,
        ]));

        $response->assertOk()
            ->assertJsonFragment(['contract_type' => 'fixed_plus_commission']);

        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => 600000,
            'commission_percentage' => 25,
            'payment_per_pickup' => null,
        ]);
    }

    public function test_pasa_a_por_retiro_y_guarda_el_monto_por_retiro(): void
    {
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'per_pickup',
            'payment_per_pickup' => 1500,
        ]));

        $response->assertOk()
            ->assertJsonFragment(['contract_type' => 'per_pickup', 'payment_per_pickup' => '1500.00']);

        // El sueldo base de Sueldo Fijo no queda colgado: el modal lo manda en null.
        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'per_pickup',
            'payment_per_pickup' => 1500,
            'base_salary' => null,
            'commission_percentage' => null,
        ]);
    }

    public function test_sueldo_fijo_sigue_guardando_igual_que_antes(): void
    {
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_salary',
            'base_salary' => 650000,
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_salary',
            'base_salary' => 650000,
            'commission_percentage' => null,
            'payment_per_pickup' => null,
        ]);
    }

    public function test_sueldo_fijo_sin_sueldo_base_se_sigue_aceptando_como_antes(): void
    {
        // El backend nunca exigió sueldo base para Sueldo Fijo (lo pide el formulario);
        // el fix no tiene que volverlo obligatorio para otros llamadores.
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_salary',
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_salary',
            'base_salary' => null,
        ]);
    }

    public function test_fijo_mas_comisiones_sin_sueldo_ni_porcentaje_devuelve_422_con_mensaje_claro(): void
    {
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_plus_commission',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Datos inválidos: El sueldo base es obligatorio para la contratación Fijo + Comisiones., El porcentaje de comisiones es obligatorio para la contratación Fijo + Comisiones.',
            ])
            ->assertJsonPath('errors.base_salary.0', 'El sueldo base es obligatorio para la contratación Fijo + Comisiones.')
            ->assertJsonPath('errors.commission_percentage.0', 'El porcentaje de comisiones es obligatorio para la contratación Fijo + Comisiones.');

        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_salary',
            'base_salary' => 600000,
        ]);
    }

    public function test_fijo_mas_comisiones_acepta_sueldo_base_cero(): void
    {
        // Los 4 cadetes "Fijo + Comisiones" de producción vienen de commission_based
        // (comisión pura) y tienen base_salary NULL: al editarlos se carga 0 y tiene que
        // guardar, no confundirse con "falta el campo".
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => 0,
            'commission_percentage' => 50,
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => 0,
            'commission_percentage' => 50,
        ]);
    }

    public function test_por_retiro_sin_monto_devuelve_422_con_mensaje_claro(): void
    {
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'per_pickup',
        ]));

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Datos inválidos: El monto por retiro es obligatorio para la contratación Por Retiro.',
            ])
            ->assertJsonPath('errors.payment_per_pickup.0', 'El monto por retiro es obligatorio para la contratación Por Retiro.');

        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_salary',
            'payment_per_pickup' => null,
        ]);
    }

    public function test_commission_based_ya_no_es_un_tipo_valido(): void
    {
        // La columna en MySQL ya no lo acepta desde la migración 2026_05_11_124500:
        // si la validación lo dejara pasar, el UPDATE reventaría con un 500.
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'commission_based',
            'base_salary' => 1000,
            'commission_percentage' => 10,
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('errors.contract_type.0', 'The selected contract type is invalid.');
    }

    public function test_dejar_la_contrasena_en_blanco_no_la_cambia(): void
    {
        // El modal manda password undefined (se omite del JSON) cuando queda en blanco.
        $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => 600000,
            'commission_percentage' => 25,
        ]))->assertOk();

        $this->assertTrue(Hash::check('clave-original', $this->usuarioTest->fresh()->password));

        // Y si llega como string vacío tampoco la pisa.
        $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'password' => '',
            'contract_type' => 'per_pickup',
            'payment_per_pickup' => 1500,
        ]))->assertOk();

        $this->assertTrue(Hash::check('clave-original', $this->usuarioTest->fresh()->password));
    }

    public function test_cargar_una_contrasena_nueva_si_la_cambia(): void
    {
        $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'password' => 'clave-nueva-123',
            'contract_type' => 'fixed_salary',
            'base_salary' => 600000,
        ]))->assertOk();

        $this->assertTrue(Hash::check('clave-nueva-123', $this->usuarioTest->fresh()->password));
    }

    public function test_pasar_de_por_retiro_a_sueldo_fijo_limpia_el_monto_por_retiro(): void
    {
        $this->usuarioTest->update(['contract_type' => 'per_pickup', 'base_salary' => null, 'payment_per_pickup' => 1500]);

        $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'contract_type' => 'fixed_salary',
            'base_salary' => 600000,
        ]))->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $this->usuarioTest->id,
            'contract_type' => 'fixed_salary',
            'base_salary' => 600000,
            'payment_per_pickup' => null,
        ]);
    }

    public function test_editar_desde_el_modal_no_borra_el_porcentaje_de_ingresos(): void
    {
        // Caso adrian lucci (id 16): cadete Fijo + Comisiones con 15% de ingresos. El
        // modal no tiene ese campo; antes cada edición lo dejaba en null.
        $cadete = User::factory()->create([
            'role' => UserRole::CADETE->value,
            'branch_id' => $this->branchTest->id,
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => null,
            'income_percentage' => 15,
            'commission_percentage' => 10,
        ]);

        $this->putJson("/api/users/{$cadete->id}", [
            'name' => $cadete->name,
            'email' => $cadete->email,
            'role' => UserRole::CADETE->value,
            'branch_id' => (string) $this->branchTest->id,
            'contract_type' => 'fixed_plus_commission',
            'base_salary' => 0,
            'commission_percentage' => 12,
            'payment_per_pickup' => null,
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $cadete->id,
            'income_percentage' => 15,
            'commission_percentage' => 12,
        ]);
    }

    public function test_editar_sin_sucursal_devuelve_422_en_castellano(): void
    {
        // "Sin sucursal asignada" del select manda branch_id vacío; antes volvía el
        // "The branch id field is required." en inglés detrás del mensaje genérico.
        $response = $this->putJson("/api/users/{$this->usuarioTest->id}", $this->payloadDelModal([
            'branch_id' => '',
            'contract_type' => 'fixed_salary',
            'base_salary' => 600000,
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('errors.branch_id.0', 'Seleccioná una sucursal.');
    }

    public function test_el_listado_devuelve_el_monto_por_retiro_para_precargar_el_modal(): void
    {
        $this->usuarioTest->update(['contract_type' => 'per_pickup', 'base_salary' => null, 'payment_per_pickup' => 1500]);

        $response = $this->getJson('/api/users?page=1&per_page=10&sort_by=name&sort_direction=asc');

        $response->assertOk();
        $fila = collect($response->json('data'))->firstWhere('id', $this->usuarioTest->id);
        $this->assertNotNull($fila);
        $this->assertSame('per_pickup', $fila['contract_type']);
        $this->assertEquals(1500, $fila['payment_per_pickup']);
    }

    public function test_alta_de_usuario_con_cada_tipo_de_contratacion(): void
    {
        // El alta tenía la misma lista vieja de tipos que la edición.
        $casos = [
            'fixed_salary' => ['base_salary' => 500000],
            'fixed_plus_commission' => ['base_salary' => 300000, 'commission_percentage' => 10],
            'per_pickup' => ['payment_per_pickup' => 2000],
        ];

        foreach ($casos as $tipo => $montos) {
            $email = "alta-{$tipo}@ryrcomisiones.com";
            $response = $this->postJson('/api/users', array_merge([
                'name' => "Alta {$tipo}",
                'email' => $email,
                'password' => 'password123',
                'role' => UserRole::CADETE->value,
                'branch_id' => $this->branchTest->id,
                'contract_type' => $tipo,
                'base_salary' => null,
                'commission_percentage' => null,
                'payment_per_pickup' => null,
            ], $montos));

            $response->assertStatus(201);
            $this->assertDatabaseHas('users', array_merge(['email' => $email, 'contract_type' => $tipo], $montos));
        }
    }

    public function test_alta_por_retiro_sin_monto_devuelve_422_con_mensaje_claro(): void
    {
        $response = $this->postJson('/api/users', [
            'name' => 'Cadete por retiro',
            'email' => 'por-retiro@ryrcomisiones.com',
            'password' => 'password123',
            'role' => UserRole::CADETE->value,
            'branch_id' => $this->branchTest->id,
            'contract_type' => 'per_pickup',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.payment_per_pickup.0', 'El monto por retiro es obligatorio para la contratación Por Retiro.');
        $this->assertDatabaseMissing('users', ['email' => 'por-retiro@ryrcomisiones.com']);
    }
}
