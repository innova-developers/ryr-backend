<?php

namespace Tests\Feature\Commissions;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\IvaStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RC-520.
 *
 * AGRO SAN GENARO reclamó que "no está sumando el IVA al monto": sus comisiones de
 * $16.940 ($14.000 + IVA) figuraban en la cuenta corriente como $14.000. El IVA se
 * aplica en el repositorio DESPUÉS de calcular el total, así que el movimiento se
 * guardaba con el neto. RC-512 corrigió el camino de edición releyendo el total ya
 * persistido; esto lo deja fijado para que no vuelva a soltarse.
 */
class CommissionIvaCurrentAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    private Destination $destination;

    private Location $originLocation;

    private Location $destinationLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'administrador']);
        // "Siempre" es la configuración con la que quedó el cliente del reclamo: paga
        // IVA aunque la comisión se cargue sin método de pago, que es lo habitual.
        $this->customer = Customer::factory()->create([
            'iva_status' => IvaStatus::ALWAYS->value,
            'auto_calculate_iva' => true,
        ]);
        $this->destination = Destination::factory()->create();
        Branch::factory()->create();
        $this->originLocation = Location::factory()->create();
        $this->destinationLocation = Location::factory()->create();

        $this->actingAs($this->user);
    }

    private function payload(float $unitPrice, string $status): array
    {
        return [
            'client_id' => $this->customer->id,
            'date' => '2026-08-24',
            'origin' => $this->destination->origin,
            'destination' => $this->destination->destination,
            'status' => $status,
            'origin_location_id' => $this->originLocation->id,
            'destination_location_id' => $this->destinationLocation->id,
            'items' => [
                [
                    'type' => CommissionItemType::ORDINARIA->value,
                    'size' => CommissionItemSize::SMALL->value,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'subtotal' => $unitPrice,
                ],
            ],
            'total' => $unitPrice,
        ];
    }

    private function debitoDe(int $commissionId): ?CurrentAccount
    {
        return CurrentAccount::where('reference', "COM-{$commissionId}")->first();
    }

    public function test_el_debito_de_cuenta_corriente_incluye_el_iva(): void
    {
        $response = $this->postJson('/api/commissions', $this->payload(14000, CommissionStatus::SOLICITUD_RECIBIDA->value));
        $response->assertStatus(201);

        $commissionId = $response->json('id') ?? $response->json('commission.id');

        $this->patchJson("/api/commissions/{$commissionId}/status", [
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ])->assertStatus(200);

        $commission = Commission::find($commissionId);
        $debito = $this->debitoDe($commissionId);

        $this->assertNotNull($debito, 'la comisión facturada quedó sin movimiento de cuenta corriente');
        $this->assertEqualsWithDelta(
            (float) $commission->total,
            (float) $debito->amount,
            0.01,
            "la comisión se facturó por {$commission->total} y el movimiento quedó en {$debito->amount}"
        );
        $this->assertTrue((bool) $commission->iva_applied, 'un cliente con IVA "siempre" tiene que pagar IVA');
    }

    public function test_editar_la_comision_deja_el_debito_con_el_total_con_iva(): void
    {
        $response = $this->postJson('/api/commissions', $this->payload(14000, CommissionStatus::SOLICITUD_RECIBIDA->value));
        $response->assertStatus(201);

        $commissionId = $response->json('id') ?? $response->json('commission.id');

        // El débito nace recién al facturar.
        $this->patchJson("/api/commissions/{$commissionId}/status", [
            'status' => CommissionStatus::PAGO_VALIDACION->value,
        ])->assertStatus(200);

        // Se reedita la comisión con otro precio: es el camino por el que en producción
        // quedó el débito en el neto.
        $this->putJson("/api/commissions/{$commissionId}", $this->payload(26000, CommissionStatus::PAGO_VALIDACION->value))
            ->assertStatus(200);

        $commission = Commission::find($commissionId);
        $debito = $this->debitoDe($commissionId);

        $this->assertNotNull($debito);
        $this->assertEqualsWithDelta(
            (float) $commission->total,
            (float) $debito->amount,
            0.01,
            "tras editar, la comisión quedó en {$commission->total} y el movimiento en {$debito->amount}"
        );
    }
}
