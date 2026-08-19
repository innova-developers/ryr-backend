<?php

namespace Tests\Feature;

use App\Services\TrackingOtpService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\TrackingOtp;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * RC-500 (TRACKING PUBLICO).
 *
 * El endpoint público devolvía sin autenticación: direcciones y teléfonos de origen y
 * destino, las notas —que en 7.174 de las 9.353 comisiones de producción traen nombres
 * de personas— y el historial completo con el nombre del empleado que tocó cada estado.
 * Como el id es un entero secuencial, se podía recorrer 1..N y bajar la agenda entera.
 */
class PublicTrackingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Commission $commission;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $branch = Branch::factory()->create();
        $this->customer = Customer::factory()->create([
            'mobile' => '3492326189',
            'email' => 'titular@example.com',
        ]);

        $this->commission = Commission::factory()->create([
            'id' => 53620,
            'client_id' => $this->customer->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => $branch->id,
            'user_id' => User::factory()->create(['role' => 'administrador', 'branch_id' => $branch->id])->id,
            'origin_location_id' => Location::factory()->create([
                'origin' => 'ROSARIO',
                'address' => 'TUCUMAN 3159',
                'phone' => '3411234567',
            ])->id,
            'destination_location_id' => Location::factory()->create(['origin' => 'SAN GENARO'])->id,
            'status' => CommissionStatus::EN_TRANSITO_DESTINO->value,
            'notes' => 'RETIRAR PEDIDO A NOMBRE DE JUAN CARBALLO',
            'tracking_code' => 'ABCD1234WXYZ',
        ]);

        CommissionLog::create([
            'commission_id' => $this->commission->id,
            'user_id' => User::factory()->create(['name' => 'Empleado Interno'])->id,
            'previous_status' => CommissionStatus::EN_PLANTA->value,
            'new_status' => CommissionStatus::EN_TRANSITO_DESTINO->value,
            'details' => 'Salió a destino',
        ]);
    }

    // --- Minimización ---

    public function test_public_payload_hides_personal_data(): void
    {
        $body = $this->getJson("/api/commissions/{$this->commission->id}/tracking")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('TUCUMAN 3159', $body, 'no debe exponer direcciones');
        $this->assertStringNotContainsString('3411234567', $body, 'no debe exponer teléfonos');
        $this->assertStringNotContainsString('CARBALLO', $body, 'no debe exponer notas con nombres');
        $this->assertStringNotContainsString('Empleado Interno', $body, 'no debe exponer empleados');
    }

    public function test_public_payload_keeps_what_is_useful(): void
    {
        $this->getJson("/api/commissions/{$this->commission->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.status_label', 'En tránsito a destino')
            ->assertJsonPath('data.origin_city', 'ROSARIO')
            ->assertJsonPath('data.destination_city', 'SAN GENARO')
            ->assertJsonPath('data.detail_requires_verification', true);
    }

    public function test_can_be_looked_up_by_opaque_code(): void
    {
        $this->getJson('/api/commissions/ABCD1234WXYZ/tracking')
            ->assertOk()
            ->assertJsonPath('data.tracking_number', $this->commission->id);
    }

    public function test_unknown_identifier_returns_404(): void
    {
        $this->getJson('/api/commissions/NOEXISTE9999/tracking')->assertStatus(404);
    }

    // --- Detalle protegido ---

    public function test_detail_without_token_is_rejected(): void
    {
        $this->getJson("/api/commissions/{$this->commission->id}/tracking/detail")->assertStatus(401);
    }

    public function test_detail_with_invalid_token_is_rejected(): void
    {
        $this->getJson("/api/commissions/{$this->commission->id}/tracking/detail?token=" . str_repeat('x', 64))
            ->assertStatus(401);
    }

    public function test_full_flow_grants_access_to_the_detail(): void
    {
        $token = $this->obtenerToken();

        $this->getJson("/api/commissions/{$this->commission->id}/tracking/detail?token={$token}")
            ->assertOk()
            ->assertJsonPath('data.notes', 'RETIRAR PEDIDO A NOMBRE DE JUAN CARBALLO')
            ->assertJsonPath('data.origin.address', 'TUCUMAN 3159');
    }

    public function test_detail_history_never_exposes_employees(): void
    {
        $token = $this->obtenerToken();

        $body = $this->getJson("/api/commissions/{$this->commission->id}/tracking/detail?token={$token}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Empleado Interno', $body);
    }

    // --- Ciclo de vida del OTP ---

    public function test_request_code_does_not_reveal_whether_the_shipment_exists(): void
    {
        $existe = $this->postJson("/api/commissions/{$this->commission->id}/tracking/request-code");
        $noExiste = $this->postJson('/api/commissions/NOEXISTE9999/tracking/request-code');

        $existe->assertOk()->assertJsonPath('success', true);
        $noExiste->assertOk()->assertJsonPath('success', true);
        $this->assertSame($existe->json('message'), $noExiste->json('message'));
    }

    public function test_code_is_stored_hashed(): void
    {
        app(TrackingOtpService::class)->emitir($this->commission);

        $otp = TrackingOtp::where('commission_id', $this->commission->id)->latest('id')->first();

        $this->assertNotNull($otp);
        $this->assertStringStartsWith('$2y$', $otp->code_hash);
    }

    public function test_masked_destination_does_not_reveal_the_contact(): void
    {
        $masked = app(TrackingOtpService::class)->emitir($this->commission);

        $this->assertStringNotContainsString('3492326189', (string) $masked);
        $this->assertStringNotContainsString('titular@example.com', (string) $masked);
    }

    public function test_wrong_code_is_rejected(): void
    {
        app(TrackingOtpService::class)->emitir($this->commission);

        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => '000000'])
            ->assertStatus(401);
    }

    public function test_code_cannot_be_used_twice(): void
    {
        $codigo = $this->emitirCodigoConocido();

        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => $codigo])
            ->assertOk();

        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => $codigo])
            ->assertStatus(401);
    }

    public function test_expired_code_is_rejected(): void
    {
        $codigo = $this->emitirCodigoConocido();

        TrackingOtp::where('commission_id', $this->commission->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => $codigo])
            ->assertStatus(401);
    }

    public function test_code_dies_after_too_many_attempts(): void
    {
        $codigo = $this->emitirCodigoConocido();

        for ($i = 0; $i < TrackingOtpService::MAX_INTENTOS; $i++) {
            $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => '111111']);
        }

        // Aun con el código correcto, ya no sirve.
        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => $codigo])
            ->assertStatus(401);
    }

    public function test_new_code_invalidates_the_previous_one(): void
    {
        $viejo = $this->emitirCodigoConocido('111111');
        $this->emitirCodigoConocido('222222');

        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => $viejo])
            ->assertStatus(401);
    }

    public function test_issuing_is_rate_limited_per_shipment(): void
    {
        $svc = app(TrackingOtpService::class);

        $this->assertNotNull($svc->emitir($this->commission));
        $this->assertNotNull($svc->emitir($this->commission));
        $this->assertNotNull($svc->emitir($this->commission));
        // El cuarto pedido dentro de la hora no manda nada.
        $this->assertNull($svc->emitir($this->commission));
    }

    public function test_customer_without_contact_gets_no_code(): void
    {
        // customers.email es NOT NULL en el esquema: vacío equivale a sin contacto.
        $this->customer->update(['mobile' => null, 'phone' => null, 'email' => '']);

        $this->assertNull(app(TrackingOtpService::class)->emitir($this->commission->fresh()));
    }

    public function test_verify_requires_six_digits(): void
    {
        $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => '123'])
            ->assertStatus(422);
    }

    /**
     * Emite un OTP y devuelve el código en claro, reemplazando el hash por uno conocido.
     */
    private function emitirCodigoConocido(string $codigo = '246813'): string
    {
        app(TrackingOtpService::class)->emitir($this->commission);

        TrackingOtp::where('commission_id', $this->commission->id)
            ->whereNull('used_at')
            ->latest('id')
            ->first()
            ->update(['code_hash' => Hash::make($codigo), 'attempts' => 0]);

        return $codigo;
    }

    private function obtenerToken(): string
    {
        $codigo = $this->emitirCodigoConocido();

        return $this->postJson("/api/commissions/{$this->commission->id}/tracking/verify-code", ['code' => $codigo])
            ->assertOk()
            ->json('token');
    }
}
