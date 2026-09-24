<?php

namespace Tests\Feature;

use App\Services\WhatsAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RC-533 — el mensaje de seguimiento no le llegaba a una parte de los clientes.
 *
 * Del 15 al 23/09/2026 GreenAPI rechazó 17 envíos en producción: 14 por el formato del
 * chatId que arma WhatsAppService con el teléfono del cliente, 2 por un número mal
 * tipeado (cliente 3580, ya corregido en la ficha) y 1 por timeout. Los 14 de formato:
 *   - con el 0 de larga distancia: "03401448230" → 54903401448230 ("invalid phone number",
 *     cliente 163, comisión 55901 del 22/09);
 *   - con dos números en el campo: "03401448659  448756" → 54903401448659448756 (cliente
 *     1819, comisión 55893: 8 rechazos entre seguimientos y encuestas).
 * Afecta a todo lo que sale por WhatsApp: seguimiento, encuesta, código de verificación
 * y campañas.
 *
 * Estos tests prueban que el chatId sale con el formato que GreenAPI acepta, NO que el
 * mensaje llegue. Un fijo con formato válido también da 200 (el 5493401493294 del
 * cliente 3546, en agosto) y no le llega a nadie: los clientes 163 y 1819 están en los
 * bloques 3401-448 que otros clientes etiquetan como fijo. Por eso, cuando el teléfono
 * se corrige o viene marcado fijo, queda un aviso en el log.
 *
 * Además, el log de cada envío guardaba la URL completa, que lleva el token de la
 * instancia en el path.
 */
class WhatsAppServiceGreenApiTest extends TestCase
{
    private const TOKEN = 'b875d09-token-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.base_url' => 'https://api.green-api.test',
            'services.whatsapp.instance_id' => '1101000001',
            'services.whatsapp.token' => self::TOKEN,
        ]);
    }

    /**
     * Teléfonos tal cual están cargados en clientes reales de producción. Con la
     * normalización vieja todos daban un chatId que GreenAPI rechaza con 400.
     */
    public static function telefonosRealesMalNormalizados(): array
    {
        return [
            'cliente 163, 0 adelante (comisión 55901)' => ['03401448230', '5493401448230'],
            'cliente 637, 0 adelante con guion' => ['03401-493245', '5493401493245'],
            'cliente 1819, dos números con doble espacio (comisión 55893)' => ['03401448659  448756', '5493401448659'],
            'cliente 954, dos números con // (comisión 55633)' => ['3401412750 // 3401405638', '5493401412750'],
            'cliente 3576, dos números con /' => ['3401520524 / 3401645359', '5493401520524'],
            'cliente 1128, el primero sin característica' => ['448057/3401405160', '5493401405160'],
            'cliente 848, dos números con nombres' => ['3401413924(matias) 3401411463(tere/mama)', '5493401413924'],
            'cliente 897, el segundo entre paréntesis' => ['3401417586 (3401407681 CELE)', '5493401417586'],
            'cliente 3322, separados por guion' => ['3471626008 - 3471602725', '5493471626008'],
            'cliente 817, característica y dos números separados por espacio' => ['03401 493250 493199', '5493401493250'],
            'cliente 528, con el 15 metido' => ['0342 155099070', '5493425099070'],
            'cliente 751, con el 15 y guion' => ['341-155821548', '5493415821548'],
            'cliente 1188, característica de 4 dígitos y 15' => ['3401 15407127', '5493401407127'],
            'cliente 3038, Buenos Aires con 0 y 15' => ['0111540565765', '5491140565765'],
            'cliente 1258, el 9 de celular sin el 54' => ['91159284176', '5491159284176'],
            'código de país y 0 de larga distancia' => ['5403401448230', '5493401448230'],
            'prefijo internacional 00' => ['0054 9 341 5004958', '5493415004958'],
        ];
    }

    #[DataProvider('telefonosRealesMalNormalizados')]
    public function test_el_chat_id_de_telefonos_reales_mal_cargados_sale_con_el_formato_que_acepta_greenapi(string $telefono, string $esperado): void
    {
        $this->assertSame($esperado . '@c.us', $this->chatIdEnviado($telefono));
    }

    /**
     * Clientes que cargaron un fijo y un celular: el mensaje tiene que ir al celular
     * aunque el fijo esté primero.
     */
    public static function fijoYCelular(): array
    {
        return [
            'cliente 662' => ['03401-498809(fijo) 3401-414063 (cel)', '5493401414063'],
            'cliente 1980' => ['03401448870fijo//3401408679', '5493401408679'],
            'cliente 592' => ['03401-493498 fijo/3401410383 monica dicapua', '5493401410383'],
            'cliente 1617, el fijo sin característica' => ['493091fijo//3416157272', '5493416157272'],
        ];
    }

    #[DataProvider('fijoYCelular')]
    public function test_si_el_campo_tiene_un_fijo_y_un_celular_se_manda_al_celular(string $telefono, string $esperado): void
    {
        $this->assertSame($esperado . '@c.us', $this->chatIdEnviado($telefono));
    }

    public function test_avisa_en_el_log_cuando_el_campo_trae_dos_numeros(): void
    {
        $logs = $this->capturarLogs();

        $this->chatIdEnviado('03401448659  448756');

        $this->assertSame([[
            'telefono_cargado' => '03401448659  448756',
            'chat_id' => '5493401448659@c.us',
            'numeros_completos' => 1,
            'elegido_marcado_fijo' => false,
        ]], $logs->avisos);
    }

    public function test_avisa_en_el_log_cuando_saltea_el_fijo_y_manda_al_celular(): void
    {
        $logs = $this->capturarLogs();

        $this->chatIdEnviado('03401-498809(fijo) 3401-414063 (cel)');

        $this->assertCount(1, $logs->avisos);
        $this->assertSame('5493401414063@c.us', $logs->avisos[0]['chat_id']);
        $this->assertSame(2, $logs->avisos[0]['numeros_completos']);
        $this->assertFalse($logs->avisos[0]['elegido_marcado_fijo']);
    }

    public function test_avisa_en_el_log_cuando_el_unico_numero_esta_marcado_fijo_aunque_greenapi_lo_acepte(): void
    {
        // Cliente 3546: GreenAPI respondió 200 en agosto y en el log quedó como enviado.
        $logs = $this->capturarLogs();

        $this->assertSame('5493401493294@c.us', $this->chatIdEnviado('3401493294 fijo'));

        $this->assertCount(1, $logs->avisos);
        $this->assertTrue($logs->avisos[0]['elegido_marcado_fijo']);
    }

    public function test_avisa_en_el_log_cuando_saca_el_cero_de_adelante(): void
    {
        // Cliente 163: sin el aviso, un fijo cargado con 0 pasaría de un 400 visible a un
        // "enviado" que no llega.
        $logs = $this->capturarLogs();

        $this->chatIdEnviado('03401448230');

        $this->assertCount(1, $logs->avisos);
        $this->assertSame('03401448230', $logs->avisos[0]['telefono_cargado']);
        $this->assertSame('5493401448230@c.us', $logs->avisos[0]['chat_id']);
    }

    public function test_no_avisa_con_un_celular_bien_cargado(): void
    {
        $logs = $this->capturarLogs();

        $this->chatIdEnviado('341 500-4958');

        $this->assertSame([], $logs->avisos);
    }

    /**
     * Formatos que ya se normalizaban bien y no pueden cambiar (el 96% de los clientes
     * activos está en el primero).
     */
    public static function telefonosQueYaAndaban(): array
    {
        return [
            'celular con característica (cliente 3135)' => ['3415004958', '5493415004958'],
            'completo con + (cliente 3124)' => ['+5493416811147', '5493416811147'],
            'con guion (cliente 628)' => ['3401-440558', '5493401440558'],
            'con espacios y guion' => ['+54 11 1234-5678', '5491112345678'],
            'con paréntesis en el código de país' => ['(54) 11 1234-5678', '5491112345678'],
            'con 54 sin el 9' => ['541123456789', '5491123456789'],
            'un solo número con doble espacio en el medio' => ['341  6885908', '5493416885908'],
            'área y número separados por barra' => ['3401/448230', '5493401448230'],
            'con UTF-8 inválido de la migración' => ["3401\xE9440558", '5493401440558'],
            'doble espacio entre el 54 y el 9' => ['+54  9 341 681-1147', '5493416811147'],
            'doble espacio entre el 54 y el 9, Buenos Aires' => ['+54  9 11 1234-5678', '5491112345678'],
            'doble espacio después de (+54)' => ['(+54)  93416811147', '5493416811147'],
            'marcado fijo, un solo número (cliente 3546)' => ['3401493294 fijo', '5493401493294'],
        ];
    }

    #[DataProvider('telefonosQueYaAndaban')]
    public function test_los_formatos_que_ya_andaban_no_cambian(string $telefono, string $esperado): void
    {
        $this->assertSame($esperado . '@c.us', $this->chatIdEnviado($telefono));
    }

    public function test_el_token_no_queda_en_el_log_de_un_envio_exitoso(): void
    {
        $logs = $this->capturarLogs();
        Http::fake(['*' => Http::response(['idMessage' => '3EB0FCBDD51DBDAF203800'], 200)]);

        $this->assertTrue(app(WhatsAppService::class)->sendMessage('3415004958', 'hola'));

        $this->assertNotEmpty($logs->all);
        foreach ($logs->all as $linea) {
            $this->assertStringNotContainsString(self::TOKEN, $linea);
        }
    }

    public function test_el_token_no_queda_en_el_log_ni_en_el_error_cuando_greenapi_lo_devuelve_en_el_path(): void
    {
        $logs = $this->capturarLogs();
        // Así responde GreenAPI un chatId inválido: con el path completo, token incluido.
        Http::fake(['*' => Http::response([
            'statusCode' => 400,
            'path' => '/waInstance1101000001/sendMessage/' . self::TOKEN,
            'message' => "Validation failed. Details: 'chatId': invalid phone number",
        ], 400)]);

        $servicio = app(WhatsAppService::class);
        $this->assertFalse($servicio->sendMessage('3415004958', 'hola'));

        $this->assertSame("HTTP 400: Validation failed. Details: 'chatId': invalid phone number", $servicio->getLastError());
        foreach ($logs->all as $linea) {
            $this->assertStringNotContainsString(self::TOKEN, $linea);
        }
    }

    public function test_el_token_no_queda_en_el_log_ni_en_el_error_con_un_timeout_de_curl(): void
    {
        // Así llegó el timeout de la comisión 55922 (23/09/2026): Guzzle agrega la URL
        // completa, token incluido, al mensaje de la excepción.
        $logs = $this->capturarLogs();
        Http::fake(['*' => Http::failedConnection(
            'cURL error 28: Operation timed out after 10001 milliseconds with 0 out of 0 bytes received '
            . '(see https://curl.haxx.se/libcurl/c/libcurl-errors.html) for '
            . 'https://api.green-api.test/waInstance1101000001/sendMessage/' . self::TOKEN
        )]);

        $servicio = app(WhatsAppService::class);
        $this->assertFalse($servicio->sendMessage('3415004958', 'hola'));

        $this->assertStringContainsString('cURL error 28', $servicio->getLastError());
        $this->assertStringNotContainsString(self::TOKEN, $servicio->getLastError());
        $this->assertNotEmpty($logs->all);
        foreach ($logs->all as $linea) {
            $this->assertStringNotContainsString(self::TOKEN, $linea);
        }
    }

    public function test_el_token_no_queda_en_el_error_de_una_campaña_con_imagen(): void
    {
        // sendFileByUrl es el envío de las campañas con imagen, y su getLastError() se
        // guarda en whatsapp_campaign_messages.error_message.
        $logs = $this->capturarLogs();
        Http::fake(['*' => Http::response([
            'statusCode' => 400,
            'path' => '/waInstance1101000001/sendFileByUrl/' . self::TOKEN,
            'message' => "Validation failed. Details: 'chatId': invalid phone number",
        ], 400)]);

        $servicio = app(WhatsAppService::class);
        $this->assertFalse($servicio->sendFileByUrl('3415004958', 'https://ryrcomisiones.com/promo.jpg', 'Promo'));

        $this->assertSame("HTTP 400: Validation failed. Details: 'chatId': invalid phone number", $servicio->getLastError());
        foreach ($logs->all as $linea) {
            $this->assertStringNotContainsString(self::TOKEN, $linea);
        }
    }

    public function test_el_token_no_queda_en_el_error_de_una_campaña_con_imagen_si_se_corta_la_conexion(): void
    {
        $logs = $this->capturarLogs();
        Http::fake(['*' => Http::failedConnection()]);

        $servicio = app(WhatsAppService::class);
        $this->assertFalse($servicio->sendFileByUrl('3415004958', 'https://ryrcomisiones.com/promo.jpg'));

        $this->assertStringContainsString('sendFileByUrl/***', $servicio->getLastError());
        $this->assertStringNotContainsString(self::TOKEN, $servicio->getLastError());
        foreach ($logs->all as $linea) {
            $this->assertStringNotContainsString(self::TOKEN, $linea);
        }
    }

    public function test_el_token_no_queda_en_el_error_de_una_imagen_subida(): void
    {
        $logs = $this->capturarLogs();
        Http::fake(['*' => Http::response('Error en /waInstance1101000001/sendFileByUpload/' . self::TOKEN, 500)]);

        $servicio = app(WhatsAppService::class);
        $this->assertFalse($servicio->sendFileByUpload('3415004958', 'contenido', 'promo.jpg'));

        $this->assertStringContainsString('HTTP 500', $servicio->getLastError());
        $this->assertStringNotContainsString(self::TOKEN, $servicio->getLastError());
        foreach ($logs->all as $linea) {
            $this->assertStringNotContainsString(self::TOKEN, $linea);
        }
    }

    public function test_el_token_no_queda_en_el_error_de_campañas_si_greenapi_contesta_sin_message(): void
    {
        Http::fake(['*' => Http::response('Error en /waInstance1101000001/sendMessage/' . self::TOKEN, 502)]);

        $servicio = app(WhatsAppService::class);
        $this->assertFalse($servicio->sendMessage('3415004958', 'hola'));

        // getLastError() se guarda en whatsapp_campaign_messages.error_message.
        $this->assertStringNotContainsString(self::TOKEN, $servicio->getLastError());
        $this->assertStringContainsString('HTTP 502', $servicio->getLastError());
    }

    private function chatIdEnviado(string $telefono): string
    {
        Http::fake(['*' => Http::response(['idMessage' => 'x'], 200)]);

        app(WhatsAppService::class)->sendMessage($telefono, 'hola');

        $enviados = Http::recorded(fn (Request $r) => str_contains($r->url(), '/sendMessage/'));
        $this->assertCount(1, $enviados);

        return $enviados->first()[0]['chatId'];
    }

    private function capturarLogs(): object
    {
        $logs = new class () {
            public array $all = [];
            public array $avisos = [];
        };

        Event::listen(MessageLogged::class, function (MessageLogged $e) use ($logs) {
            $logs->all[] = $e->message . ' ' . json_encode($e->context);
            if ($e->message === 'Teléfono de WhatsApp a revisar en la ficha del cliente') {
                $logs->avisos[] = $e->context;
            }
        });

        return $logs;
    }
}
