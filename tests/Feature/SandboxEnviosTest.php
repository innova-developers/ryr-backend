<?php

namespace Tests\Feature;

use App\Services\FcmNotificationService;
use App\Services\SandboxEnvios;
use App\Services\WhatsAppService;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El 24/09/2026 una simulación contra una copia de producción en local mandó 104 pushes
 * reales de "Comercio por cerrar" a dos cadetes. Local y dev trabajan sobre copias de
 * producción, con teléfonos, mails y tokens reales, y dev usa la instancia de WhatsApp de
 * producción. Fuera de producción, sólo puede salir algo a los destinatarios de prueba.
 */
class SandboxEnviosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml lo apaga para el resto de la suite; acá se prueba prendido.
        config([
            'services.sandbox.activo' => null,
            'services.sandbox.telefonos' => '3416811147,2915662430',
            'services.sandbox.usuarios_push' => '98',
            'services.sandbox.mails' => 'juan@innovadevelopers.com',
            'services.whatsapp.base_url' => 'https://api.green-api.test',
        ]);
    }

    private function whatsapp(): WhatsAppService
    {
        // Credenciales de mentira: sin ellas el servicio corta antes de llegar al sandbox.
        config(['services.whatsapp.instance_id' => '1101000', 'services.whatsapp.token' => 'token-de-prueba']);

        return new WhatsAppService();
    }

    public function test_fuera_de_produccion_esta_activo_por_defecto_y_en_produccion_no(): void
    {
        $this->assertTrue(SandboxEnvios::activo(), 'en testing/local/dev tiene que estar activo sin configurar nada');

        $this->app['env'] = 'production';
        $this->assertFalse(SandboxEnvios::activo(), 'en producción no puede bloquear nada');
    }

    public function test_un_whatsapp_a_un_cliente_real_no_sale(): void
    {
        Http::fake(['*' => Http::response(['idMessage' => 'X'], 200)]);

        $enviado = $this->whatsapp()->sendMessage('3401448230', 'Tu comisión fue cargada');

        $this->assertFalse($enviado);
        Http::assertNothingSent();
    }

    public function test_un_whatsapp_a_un_telefono_de_prueba_sale_en_cualquier_formato(): void
    {
        Http::fake(['*' => Http::response(['idMessage' => 'X'], 200)]);
        $servicio = $this->whatsapp();

        foreach (['2915662430', '02915662430', '+54 9 291 566-2430', '5493416811147'] as $telefono) {
            $this->assertTrue($servicio->sendMessage($telefono, 'prueba'), "no salió a {$telefono}");
        }

        Http::assertSentCount(4);
    }

    public function test_el_bloqueo_queda_en_el_log_sin_el_numero_completo(): void
    {
        Http::fake();
        Log::spy();

        $this->whatsapp()->sendMessage('3401448230', 'hola');

        Log::shouldHaveReceived('info')->withArgs(function ($mensaje, $contexto = []) {
            return $mensaje === 'Envío bloqueado por sandbox'
                && $contexto['canal'] === 'whatsapp'
                && $contexto['destino'] === '***8230';
        })->once();
    }

    public function test_un_push_a_un_cadete_real_no_sale(): void
    {
        $cadete = User::factory()->create(['role' => 'cadete']);

        $resultado = app(FcmNotificationService::class)->sendPushToUser($cadete->id, [
            'title' => '⚠️ Comercio por cerrar',
            'body' => 'El origen cerrará en aproximadamente 30 minutos',
        ]);

        $this->assertSame(0, $resultado['sent']);
        $this->assertStringContainsString('sandbox', $resultado['message']);
    }

    public function test_el_push_masivo_a_todos_los_cadetes_tambien_se_filtra(): void
    {
        User::factory()->count(3)->create(['role' => 'cadete']);

        $resultado = app(FcmNotificationService::class)->sendPushToUsers(
            User::where('role', 'cadete')->pluck('id')->all(),
            ['title' => 'Nueva comisión disponible', 'body' => 'Hay una comisión para retirar']
        );

        $this->assertSame(0, $resultado['successful']);
        foreach ($resultado['details'] as $detalle) {
            $this->assertStringContainsString('sandbox', $detalle['message']);
        }
    }

    public function test_el_cadete_de_prueba_pasa_el_sandbox(): void
    {
        // Pasa el filtro y sigue el camino normal: acá termina en "Firebase no está
        // configurado" porque los tests no tienen credenciales, no en el bloqueo.
        $resultado = app(FcmNotificationService::class)->sendPushToUser(98, [
            'title' => 'Prueba', 'body' => 'Prueba',
        ]);

        $this->assertStringNotContainsString('sandbox', $resultado['message']);
    }

    public function test_un_mail_a_un_cliente_real_no_sale_y_uno_de_prueba_si(): void
    {
        config(['mail.default' => 'array']);

        Mail::raw('Encuesta', fn ($m) => $m->to('cliente.real@gmail.com')->subject('Tu opinión nos importa'));
        Mail::raw('Prueba', fn ($m) => $m->to('juan@innovadevelopers.com')->subject('Prueba'));

        $enviados = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $enviados);
        $this->assertSame('juan@innovadevelopers.com', $enviados[0]->getOriginalMessage()->getTo()[0]->getAddress());
    }

    public function test_un_mail_con_un_destinatario_real_en_copia_no_sale(): void
    {
        config(['mail.default' => 'array']);

        Mail::raw('Factura', fn ($m) => $m->to('juan@innovadevelopers.com')->cc('cliente.real@gmail.com')->subject('Factura'));

        $this->assertCount(0, app('mailer')->getSymfonyTransport()->messages());
    }

    public function test_en_produccion_no_bloquea_nada(): void
    {
        $this->app['env'] = 'production';
        Http::fake(['*' => Http::response(['idMessage' => 'X'], 200)]);
        config(['mail.default' => 'array']);

        $this->assertTrue($this->whatsapp()->sendMessage('3401448230', 'Tu comisión fue cargada'));
        Mail::raw('Encuesta', fn ($m) => $m->to('cliente.real@gmail.com')->subject('Encuesta'));

        Http::assertSentCount(1);
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages());
    }
}
