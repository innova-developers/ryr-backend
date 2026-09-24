<?php

namespace Tests\Feature;

use App\Services\FcmNotificationService;
use App\Services\ScheduleNormalizer\ScheduleNormalizerService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Branch;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use App\Shared\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aviso de "Comercio por cerrar" (cadetes:notify-location-closing), que se activa al poner
 * el cron en producción.
 *
 * El comando corre cada 5 minutos con una ventana de 30. Simulado sobre una copia de
 * producción el 24/09/2026, le repetía el mismo aviso al cadete hasta 12 veces, y el texto
 * decía siempre "cerrará en aproximadamente 30 minutos" aunque faltaran 5.
 */
class NotifyCadetesLocationClosingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{user: int, body: string}> */
    private array $envios = [];

    private array $respuestas = [];

    private User $cadete;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cadete = User::factory()->create(['role' => 'cadete']);

        $this->mock(FcmNotificationService::class, function ($mock) {
            $mock->shouldReceive('sendPushToUser')->andReturnUsing(function (int $userId, array $payload) {
                $this->envios[] = ['user' => $userId, 'body' => $payload['body'], 'minutos' => $payload['data']['minutes_until_close']];
                $sent = array_shift($this->respuestas) ?? 1;

                return ['success' => $sent > 0, 'message' => '', 'sent' => $sent, 'failed' => $sent > 0 ? 0 : 1, 'invalid_tokens' => []];
            });
        });
    }

    private function comision(?string $horarioOrigen, ?string $horarioDestino = null): Commission
    {
        return Commission::factory()->create([
            'client_id' => Customer::factory()->create()->id,
            'destination_id' => Destination::factory()->create()->id,
            'branch_id' => Branch::factory()->create()->id,
            'cadete_id' => $this->cadete->id,
            'status' => CommissionStatus::CADETE_ASIGNADO->value,
            'origin_location_id' => Location::factory()->create(['name' => 'DEPOSITO RYR', 'schedule' => $horarioOrigen ?? ''])->id,
            'destination_location_id' => Location::factory()->create(['name' => 'CERNETTI', 'schedule' => $horarioDestino ?? ''])->id,
        ]);
    }

    private function correrA(string $fechaHora): void
    {
        Carbon::setTestNow(Carbon::parse($fechaHora));
        $this->artisan('cadetes:notify-location-closing')->assertSuccessful();
        Carbon::setTestNow();
    }

    public function test_avisa_una_sola_vez_aunque_el_comando_corra_cada_5_minutos(): void
    {
        $this->comision('8:30-16:30');

        foreach (['16:00', '16:05', '16:10', '16:15', '16:20', '16:25'] as $hora) {
            $this->correrA("2026-09-23 {$hora}");
        }

        $this->assertCount(1, $this->envios, 'el cadete tiene que recibir un solo aviso por comercio');
        $this->assertStringContainsString('cerrará en aproximadamente 30 minutos', $this->envios[0]['body']);
    }

    public function test_el_texto_dice_los_minutos_que_faltan_de_verdad(): void
    {
        $this->comision('8:30-16:30');

        $this->correrA('2026-09-23 16:22');

        $this->assertCount(1, $this->envios);
        $this->assertStringContainsString('cerrará en aproximadamente 8 minutos', $this->envios[0]['body']);
        $this->assertSame(8, $this->envios[0]['minutos']);
    }

    public function test_si_el_push_no_sale_se_reintenta_en_la_corrida_siguiente(): void
    {
        $this->comision('8:30-16:30');
        $this->respuestas = [0, 1];

        $this->correrA('2026-09-23 16:05');
        $this->correrA('2026-09-23 16:10');
        $this->correrA('2026-09-23 16:15');

        $this->assertCount(2, $this->envios, 'falla, reintenta y sale; después no vuelve a avisar');
    }

    public function test_al_dia_siguiente_vuelve_a_avisar(): void
    {
        $this->comision('8:30-16:30');

        $this->correrA('2026-09-23 16:10');
        $this->correrA('2026-09-24 16:10');

        $this->assertCount(2, $this->envios);
    }

    public function test_origen_y_destino_se_avisan_por_separado_y_una_vez_cada_uno(): void
    {
        $this->comision('8:30-16:30', '9:00-16:40');

        $this->correrA('2026-09-23 16:15');
        $this->correrA('2026-09-23 16:20');

        $this->assertCount(2, $this->envios);
    }

    public function test_fuera_de_la_ventana_no_avisa(): void
    {
        $this->comision('8:30-16:30');

        $this->correrA('2026-09-23 11:40');
        $this->correrA('2026-09-23 16:35');

        $this->assertCount(0, $this->envios);
    }

    public function test_minutos_hasta_el_cierre_toma_el_rango_mas_proximo_y_cruza_la_medianoche(): void
    {
        $servicio = app(ScheduleNormalizerService::class);
        $rangos = [['start' => '08:30', 'end' => '12:30'], ['start' => '15:00', 'end' => '19:30']];

        Carbon::setTestNow(Carbon::parse('2026-09-23 12:10'));
        $this->assertSame(20, $servicio->minutesUntilClose($rangos, 30));

        Carbon::setTestNow(Carbon::parse('2026-09-23 13:00'));
        $this->assertNull($servicio->minutesUntilClose($rangos, 30));

        Carbon::setTestNow(Carbon::parse('2026-09-23 23:50'));
        $this->assertSame(20, $servicio->minutesUntilClose([['start' => '20:00', 'end' => '00:10']], 30));

        Carbon::setTestNow();
    }
}
