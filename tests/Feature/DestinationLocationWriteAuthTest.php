<?php

namespace Tests\Feature;

use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alta, edición y baja de destinos y locaciones estaban publicadas sin ningún
 * middleware: cualquiera, sin loguearse, podía borrar un destino o cambiarle el precio.
 * En producción hay 490 destinos dados de baja y en septiembre entraron 1.773 DELETE a
 * /destinations; vinieron de las IPs de la oficina, pero el endpoint no pedía nada.
 *
 * Las lecturas tienen que seguir públicas: el cotizador de la landing (LandingQuoteForm)
 * consulta orígenes, destinos, tarifas y locaciones sin sesión.
 */
class DestinationLocationWriteAuthTest extends TestCase
{
    use RefreshDatabase;

    private function destinoValido(): array
    {
        return [
            'origin' => 'ROSARIO',
            'destination' => 'SAN GENARO',
            'fixed_price' => 10000,
            'small_bulk_price' => 4000,
            'large_bulk_price' => 6000,
        ];
    }

    private function locacionValida(): array
    {
        return [
            'name' => 'Deposito de prueba',
            'address' => 'Rivadavia 100',
            'origin' => 'ROSARIO',
            'phone' => '3416811147',
            'schedule' => '9:00 - 18:00',
        ];
    }

    public function test_sin_sesion_no_se_puede_borrar_un_destino(): void
    {
        $destino = Destination::factory()->create();

        $this->deleteJson("/api/destinations/{$destino->id}")->assertStatus(401);

        $this->assertNull(Destination::find($destino->id)?->deleted_at, 'el destino se borró sin sesión');
        $this->assertNotNull(Destination::find($destino->id));
    }

    public function test_sin_sesion_no_se_puede_cambiar_el_precio_de_un_destino(): void
    {
        $destino = Destination::factory()->create(['fixed_price' => 10000]);

        $this->putJson("/api/destinations/{$destino->id}", ['fixed_price' => 1] + $this->destinoValido())
            ->assertStatus(401);

        $this->assertEquals(10000, (float) Destination::find($destino->id)->fixed_price);
    }

    public function test_sin_sesion_no_se_puede_crear_un_destino(): void
    {
        $antes = Destination::count();

        $this->postJson('/api/destinations', $this->destinoValido())->assertStatus(401);

        $this->assertSame($antes, Destination::count());
    }

    public function test_sin_sesion_no_se_puede_crear_editar_ni_borrar_una_locacion(): void
    {
        $locacion = Location::factory()->create(['name' => 'Original']);

        $this->postJson('/api/locations', $this->locacionValida())->assertStatus(401);
        $this->putJson("/api/locations/{$locacion->id}", ['name' => 'Pisado'] + $this->locacionValida())->assertStatus(401);
        $this->deleteJson("/api/locations/{$locacion->id}")->assertStatus(401);

        $this->assertSame('Original', Location::find($locacion->id)->name);
    }

    public function test_un_cliente_del_portal_no_puede_borrar_destinos(): void
    {
        // Los clientes del portal también tienen token de Sanctum: auth:sanctum solo no alcanza.
        $cliente = User::factory()->create(['role' => 'cliente']);
        $destino = Destination::factory()->create();

        $this->actingAs($cliente, 'sanctum')
            ->deleteJson("/api/destinations/{$destino->id}")
            ->assertStatus(403);

        $this->assertNotNull(Destination::find($destino->id));
    }

    public function test_mostrador_y_cobrador_siguen_cargando_destinos_y_locaciones(): void
    {
        // Mostradores y cobradores crean destinos y locaciones desde Nueva Comisión y
        // Nuevo Presupuesto; el front no filtra esas pantallas por rol.
        foreach (['mostrador', 'cobrador', 'administrador'] as $i => $rol) {
            $usuario = User::factory()->create(['role' => $rol]);

            $this->actingAs($usuario, 'sanctum')
                ->postJson('/api/destinations', ['destination' => "DESTINO {$i}"] + $this->destinoValido())
                ->assertSuccessful();

            $this->actingAs($usuario, 'sanctum')
                ->postJson('/api/locations', ['name' => "Locacion {$rol}"] + $this->locacionValida())
                ->assertSuccessful();
        }
    }

    public function test_el_cotizador_de_la_landing_sigue_leyendo_sin_sesion(): void
    {
        Destination::factory()->create($this->destinoValido());
        Location::factory()->create(['origin' => 'ROSARIO']);

        $this->getJson('/api/destinations/origin/ROSARIO')->assertStatus(200);
        $this->getJson('/api/destinations/rates/ROSARIO/SAN%20GENARO')->assertStatus(200);
        $this->getJson('/api/locations/origin/ROSARIO')->assertStatus(200);
        $this->getJson('/api/origins')->assertStatus(200);
    }
}
