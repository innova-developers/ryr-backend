<?php

namespace Tests\Feature\Customers;

use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-543.
 *
 * El update de clientes trataba todo campo ausente como si lo hubieran vaciado. El Pool
 * de Cobranzas asigna el cobrador mandando sólo los datos básicos del cliente, y cada
 * asignación le apagaba el premium, le prendía el IVA automático, lo movía a la sucursal
 * del cobrador y le borraba CUIT, mapa, horarios y observaciones. El formulario de
 * Clientes tampoco manda premium, mapa, horarios ni sucursal, así que cualquier edición
 * normal hacía lo mismo. No hay auditoría en customers: el daño hacia atrás no se puede
 * reconstruir, por eso importa que no vuelva a pasar.
 */
class CustomerPartialUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Branch $sucursalDelCliente;

    private Branch $sucursalDelCobrador;

    private User $cobrador;

    private Customer $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sucursalDelCliente = Branch::factory()->create();
        $this->sucursalDelCobrador = Branch::factory()->create();
        $this->cobrador = User::factory()->create([
            'role' => 'cobrador',
            'branch_id' => $this->sucursalDelCobrador->id,
        ]);

        $this->cliente = Customer::factory()->create([
            'branch_id' => $this->sucursalDelCliente->id,
            'is_premium' => true,
            'auto_calculate_iva' => false,
            'iva_status' => 'always',
            'cuit' => '30-71234567-8',
            'maps_url' => 'https://maps.google.com/?q=rivadavia+100',
            'business_hours' => 'Lunes a viernes 8 a 17',
            'observations' => 'Tocar timbre del depósito',
            'internal_user_id' => null,
        ]);
    }

    private function assertFichaIntacta(): void
    {
        $c = Customer::find($this->cliente->id);

        $this->assertTrue((bool) $c->is_premium, 'se apagó el premium');
        $this->assertFalse((bool) $c->auto_calculate_iva, 'se prendió el IVA automático');
        $this->assertSame('always', $c->iva_status?->value ?? $c->iva_status, 'cambió el estado de IVA');
        $this->assertSame($this->sucursalDelCliente->id, (int) $c->branch_id, 'el cliente cambió de sucursal');
        $this->assertSame('30-71234567-8', $c->cuit, 'se borró el CUIT');
        $this->assertSame('https://maps.google.com/?q=rivadavia+100', $c->maps_url, 'se borró el mapa');
        $this->assertSame('Lunes a viernes 8 a 17', $c->business_hours, 'se borraron los horarios');
        $this->assertSame('Tocar timbre del depósito', $c->observations, 'se borraron las observaciones');
        $this->assertSame($this->cliente->name, $c->name);
        $this->assertSame($this->cliente->email, $c->email);
    }

    public function test_asignarse_un_cliente_desde_el_pool_no_toca_el_resto_de_la_ficha(): void
    {
        $this->actingAs($this->cobrador, 'sanctum')
            ->putJson("/api/customers/{$this->cliente->id}", ['internal_user_id' => $this->cobrador->id])
            ->assertStatus(200);

        $this->assertSame($this->cobrador->id, (int) Customer::find($this->cliente->id)->internal_user_id);
        $this->assertFichaIntacta();
    }

    public function test_desasignar_desde_el_pool_no_toca_el_resto_de_la_ficha(): void
    {
        $this->cliente->update(['internal_user_id' => $this->cobrador->id]);

        $this->actingAs($this->cobrador, 'sanctum')
            ->putJson("/api/customers/{$this->cliente->id}", ['internal_user_id' => null])
            ->assertStatus(200);

        $this->assertNull(Customer::find($this->cliente->id)->internal_user_id);
        $this->assertFichaIntacta();
    }

    public function test_el_payload_viejo_del_pool_tampoco_pisa_la_ficha(): void
    {
        // Lo que mandaba el pool hasta RC-543, por si queda un front cacheado en algún
        // navegador después del deploy.
        $this->actingAs($this->cobrador, 'sanctum')
            ->putJson("/api/customers/{$this->cliente->id}", [
                'dni' => $this->cliente->dni,
                'name' => $this->cliente->name,
                'last_name' => $this->cliente->last_name,
                'email' => $this->cliente->email,
                'mobile' => $this->cliente->mobile,
                'address' => $this->cliente->address,
                'city' => $this->cliente->city,
                'phone' => $this->cliente->phone,
                'internal_user_id' => $this->cobrador->id,
            ])
            ->assertStatus(200);

        $this->assertFichaIntacta();
    }

    public function test_editar_desde_clientes_no_mueve_al_cliente_a_la_sucursal_de_quien_edita(): void
    {
        $mostrador = User::factory()->create(['role' => 'mostrador', 'branch_id' => $this->sucursalDelCobrador->id]);

        // El formulario de Clientes no manda premium, mapa, horarios ni sucursal.
        $this->actingAs($mostrador, 'sanctum')
            ->putJson("/api/customers/{$this->cliente->id}", [
                'type' => 'individual',
                'name' => $this->cliente->name,
                'last_name' => $this->cliente->last_name,
                'dni' => $this->cliente->dni,
                'email' => $this->cliente->email,
                'phone' => '3416811147',
                'address' => 'Rivadavia 200',
                'city' => $this->cliente->city,
                'cuit' => '30-71234567-8',
                'auto_calculate_iva' => false,
                'iva_status' => 'always',
                'observations' => 'Tocar timbre del depósito',
            ])
            ->assertStatus(200);

        $c = Customer::find($this->cliente->id);
        $this->assertSame('Rivadavia 200', $c->address, 'el cambio pedido no se guardó');
        $this->assertFichaIntacta();
    }

    public function test_los_cambios_explicitos_se_siguen_guardando(): void
    {
        $admin = User::factory()->create(['role' => 'administrador', 'branch_id' => $this->sucursalDelCliente->id]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/customers/{$this->cliente->id}", [
                'is_premium' => false,
                'auto_calculate_iva' => true,
                'branch_id' => $this->sucursalDelCobrador->id,
                'observations' => null,
            ])
            ->assertStatus(200);

        $c = Customer::find($this->cliente->id);
        $this->assertFalse((bool) $c->is_premium);
        $this->assertTrue((bool) $c->auto_calculate_iva);
        $this->assertSame($this->sucursalDelCobrador->id, (int) $c->branch_id);
        $this->assertNull($c->observations, 'vaciar un campo a propósito tiene que seguir funcionando');
        $this->assertSame('30-71234567-8', $c->cuit, 'lo que no se mandó no se toca');
    }

    public function test_un_cliente_sin_sucursal_toma_la_de_quien_edita(): void
    {
        $this->cliente->update(['branch_id' => null]);

        $this->actingAs($this->cobrador, 'sanctum')
            ->putJson("/api/customers/{$this->cliente->id}", ['internal_user_id' => $this->cobrador->id])
            ->assertStatus(200);

        $this->assertSame($this->sucursalDelCobrador->id, (int) Customer::find($this->cliente->id)->branch_id);
    }

    public function test_un_email_vacio_no_borra_el_email_ni_da_500(): void
    {
        // customers.email es NOT NULL. Antes, un email vacío o en null en el request
        // terminaba en un TypeError del DTO y la pantalla mostraba un 500.
        foreach (['', null] as $vacio) {
            $this->actingAs($this->cobrador, 'sanctum')
                ->putJson("/api/customers/{$this->cliente->id}", ['email' => $vacio, 'internal_user_id' => $this->cobrador->id])
                ->assertStatus(200);

            $this->assertSame($this->cliente->email, Customer::find($this->cliente->id)->email);
        }
    }
}
