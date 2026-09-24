<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;
    private ?string $instanceId;
    private ?string $token;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url', 'https://api.green-api.com');
        $this->instanceId = config('services.whatsapp.instance_id') ?? '';
        $this->token = config('services.whatsapp.token') ?? '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        $this->lastError = null;

        try {
            if (empty($this->instanceId) || empty($this->token)) {
                $this->lastError = 'Credenciales WhatsApp no configuradas';
                Log::error('WhatsApp credentials not configured', [
                    'phone' => $phone,
                    'instance_id_set' => ! empty($this->instanceId),
                    'token_set' => ! empty($this->token),
                ]);

                return false;
            }

            $cleanPhone = $this->cleanPhoneNumber($phone);
            $chatId = $cleanPhone . '@c.us';

            if (! SandboxEnvios::permiteTelefono($cleanPhone)) {
                SandboxEnvios::bloquear('whatsapp', $cleanPhone);
                $this->lastError = 'Bloqueado por sandbox: fuera de producción sólo se envía a teléfonos de prueba';

                return false;
            }

            // URL de GreenAPI: https://api.green-api.com/waInstance{instanceId}/sendMessage/{token}
            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/sendMessage/{$this->token}";
            $payload = [
                'chatId' => $chatId,
                'message' => $message,
            ];

            // RC-533: la URL lleva el token de la instancia; se logueaba entera y el
            // laravel.log de producción quedó con el token en cada envío.
            Log::info('Attempting to send WhatsApp message via GreenAPI', [
                'phone' => $cleanPhone,
                'chat_id' => $chatId,
                'base_url' => $this->baseUrl,
                'instance_id' => $this->instanceId,
            ]);

            $response = Http::timeout(30)->post($url, $payload);

            // GreenAPI devuelve el path (con el token) en el cuerpo de los errores.
            $body = $this->ocultarToken($response->body());
            $responseData = json_decode($body, true);
            $statusCode = $response->status();

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully via GreenAPI', [
                    'phone' => $cleanPhone,
                    'chat_id' => $chatId,
                    'response' => $responseData,
                    'status' => $statusCode,
                ]);

                return true;
            } else {
                $this->lastError = "HTTP {$statusCode}: " . ($responseData['message'] ?? $body);
                Log::error('Failed to send WhatsApp message via GreenAPI', [
                    'phone' => $cleanPhone,
                    'chat_id' => $chatId,
                    'response' => $responseData,
                    'status' => $statusCode,
                    'body' => $body,
                ]);

                return false;
            }
        } catch (\Exception $e) {
            // Los errores de conexión (cURL 28) incluyen la URL completa en el mensaje.
            $this->lastError = $this->ocultarToken($e->getMessage());
            Log::error('Exception while sending WhatsApp message via GreenAPI', [
                'phone' => $phone,
                'error' => $this->lastError,
                'trace' => $this->ocultarToken($e->getTraceAsString()),
            ]);

            return false;
        }
    }

    public function sendFileByUrl(string $phone, string $fileUrl, ?string $caption = null): bool
    {
        $this->lastError = null;

        try {
            if (empty($this->instanceId) || empty($this->token)) {
                $this->lastError = 'Credenciales WhatsApp no configuradas';

                return false;
            }

            $cleanPhone = $this->cleanPhoneNumber($phone);
            $chatId = $cleanPhone . '@c.us';

            if (! SandboxEnvios::permiteTelefono($cleanPhone)) {
                SandboxEnvios::bloquear('whatsapp', $cleanPhone);
                $this->lastError = 'Bloqueado por sandbox: fuera de producción sólo se envía a teléfonos de prueba';

                return false;
            }
            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/sendFileByUrl/{$this->token}";

            $payload = [
                'chatId' => $chatId,
                'urlFile' => $fileUrl,
                'fileName' => basename(parse_url($fileUrl, PHP_URL_PATH)) ?: 'image.jpg',
            ];
            if ($caption) {
                $payload['caption'] = $caption;
            }

            $response = Http::timeout(30)->post($url, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp file sent via GreenAPI', ['phone' => $cleanPhone, 'file' => $fileUrl]);

                return true;
            }

            $body = $this->ocultarToken($response->body());
            $this->lastError = "HTTP {$response->status()}: " . (json_decode($body, true)['message'] ?? $body);
            Log::error('Failed to send WhatsApp file', ['phone' => $cleanPhone, 'response' => json_decode($body, true)]);

            return false;
        } catch (\Exception $e) {
            $this->lastError = $this->ocultarToken($e->getMessage());
            Log::error('Exception sending WhatsApp file', ['phone' => $phone, 'error' => $this->lastError]);

            return false;
        }
    }

    public function sendFileByUpload(string $phone, string $fileContent, string $fileName, ?string $caption = null): bool
    {
        $this->lastError = null;

        try {
            if (empty($this->instanceId) || empty($this->token)) {
                $this->lastError = 'Credenciales WhatsApp no configuradas';

                return false;
            }

            $cleanPhone = $this->cleanPhoneNumber($phone);
            $chatId = $cleanPhone . '@c.us';

            if (! SandboxEnvios::permiteTelefono($cleanPhone)) {
                SandboxEnvios::bloquear('whatsapp', $cleanPhone);
                $this->lastError = 'Bloqueado por sandbox: fuera de producción sólo se envía a teléfonos de prueba';

                return false;
            }
            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/sendFileByUpload/{$this->token}";

            $response = Http::timeout(60)
                ->attach('file', $fileContent, $fileName)
                ->post($url, [
                    'chatId' => $chatId,
                    'caption' => $caption ?? '',
                ]);

            if ($response->successful()) {
                Log::info('WhatsApp file uploaded via GreenAPI', ['phone' => $cleanPhone, 'file' => $fileName]);

                return true;
            }

            $body = $this->ocultarToken($response->body());
            $this->lastError = "HTTP {$response->status()}: " . (json_decode($body, true)['message'] ?? $body);
            Log::error('Failed to upload WhatsApp file', ['phone' => $cleanPhone, 'response' => json_decode($body, true)]);

            return false;
        } catch (\Exception $e) {
            $this->lastError = $this->ocultarToken($e->getMessage());
            Log::error('Exception uploading WhatsApp file', ['phone' => $phone, 'error' => $this->lastError]);

            return false;
        }
    }

    /**
     * El token va en el path de todas las URLs de GreenAPI. getLastError() termina
     * guardado en whatsapp_campaign_messages.error_message, así que tampoco puede llevarlo.
     */
    private function ocultarToken(string $texto): string
    {
        return empty($this->token) ? $texto : str_replace($this->token, '***', $texto);
    }

    /**
     * RC-533: arma el celular argentino (549 + 10 dígitos) que GreenAPI espera en el chatId.
     *
     * Lo que ya salía así se devuelve igual que siempre, para que ningún número que hoy
     * sale bien pueda cambiar. Sólo se toca lo que GreenAPI rechazaba con 400: del 15 al
     * 23/09/2026 fueron 14 envíos, por el 0 de larga distancia ("03401448230" quedaba
     * 54903401448230) o por dos números en el mismo campo ("03401448659  448756" quedaba
     * 54903401448659448756).
     */
    private function cleanPhoneNumber(string $phone): string
    {
        $digitos = preg_replace('/[^0-9]/', '', $phone);
        $chatId = $this->normalizacionOriginal($digitos);
        $corregido = ! preg_match('/^549\d{10}$/', $chatId);
        $marcadoFijo = stripos($phone, 'fijo') !== false;

        if (! $corregido && ! $marcadoFijo) {
            return $chatId;
        }

        $numeros = $this->numerosDelCampo($phone);

        if ($corregido) {
            // Si el cliente marcó alguno como fijo, se usa el que no lo es.
            $elegido = collect($numeros)->firstWhere('fijo', false) ?? ($numeros[0] ?? null);
            $chatId = '549' . ($elegido['numero'] ?? $this->numeroNacional($digitos));
        }

        // Un fijo con formato válido GreenAPI lo acepta (200 e idMessage, como el
        // 5493401493294 del cliente 3546 en agosto), pero el mensaje no le llega a nadie y
        // en el log queda como enviado. Antes el 400 era la única pista de estas fichas;
        // ahora es este aviso, para pedirle un celular al cliente.
        Log::warning('Teléfono de WhatsApp a revisar en la ficha del cliente', [
            'telefono_cargado' => $phone,
            'chat_id' => $chatId . '@c.us',
            'numeros_completos' => count($numeros),
            'elegido_marcado_fijo' => (bool) (collect($numeros)->firstWhere('numero', substr($chatId, 3))['fijo'] ?? $marcadoFijo),
        ]);

        return $chatId;
    }

    /**
     * Normalización de siempre, sin cambios: sólo agrega el código de país.
     */
    private function normalizacionOriginal(string $digitos): string
    {
        // Si ya tiene el formato completo 549XXXXXXXXX, devolverlo tal como está
        if (str_starts_with($digitos, '549')) {
            return $digitos;
        }

        // Si tiene formato 54XXXXXXXXX, agregar el 9 después del 54
        if (str_starts_with($digitos, '54')) {
            return '54' . '9' . substr($digitos, 2);
        }

        // Si no tiene código de país, agregar 549 al principio
        return '549' . $digitos;
    }

    /**
     * Números completos (característica + número) escritos en el campo, en orden, con los
     * que el cliente etiquetó como fijo. La etiqueta va después del número, como está
     * cargada en producción: "03401-498809(fijo) 3401-414063 (cel)",
     * "03401448870fijo//3401408679".
     *
     * Los tramos de dígitos se juntan hasta completar un número ("03401 493250 493199"). Si
     * se pasan de largo, lo anterior era un número sin característica y se descarta
     * ("448057/3401405160").
     *
     * @return list<array{numero: string, fijo: bool}>
     */
    private function numerosDelCampo(string $phone): array
    {
        // Sin /u: los dígitos son ASCII y así no falla con el UTF-8 inválido de la migración.
        preg_match_all('/\d+/', $phone, $coincidencias, PREG_OFFSET_CAPTURE);
        $tramos = $coincidencias[0];

        $numeros = [];
        $digitos = '';
        $etiqueta = '';

        foreach ($tramos as $i => [$tramo, $inicio]) {
            $fin = $inicio + strlen($tramo);
            $texto = substr($phone, $fin, ($tramos[$i + 1][1] ?? strlen($phone)) - $fin);

            if (strlen($this->numeroNacional($digitos . $tramo)) > 10) {
                $digitos = '';
                $etiqueta = '';
            }

            $digitos .= $tramo;
            $etiqueta .= $texto;
            $nacional = $this->numeroNacional($digitos);

            if (strlen($nacional) === 10) {
                $numeros[] = ['numero' => $nacional, 'fijo' => stripos($etiqueta, 'fijo') !== false];
            }

            if (strlen($nacional) >= 10) {
                $digitos = '';
                $etiqueta = '';
            }
        }

        return $numeros;
    }

    /**
     * Número nacional (característica + número, 10 dígitos) a partir de cómo lo escribe la
     * gente: sin el 00 internacional ni el 0 de larga distancia, sin el 54 ni el 9 de
     * celular, y sin el 15 metido entre la característica y el número ("0342 155099070").
     * Si no llega a 10 dígitos devuelve lo que quede.
     */
    private function numeroNacional(string $digitos): string
    {
        $numero = ltrim($digitos, '0');

        if (str_starts_with($numero, '54')) {
            $numero = substr($numero, 2);
            if (str_starts_with($numero, '9')) {
                $numero = substr($numero, 1);
            }
            $numero = ltrim($numero, '0');
        } elseif (strlen($numero) === 11 && str_starts_with($numero, '9')) {
            // Las características empiezan con 1, 2 o 3: un 9 adelante es el de celular.
            $numero = substr($numero, 1);
        }

        // La característica es 11, o de 3 o 4 dígitos que empiezan con 2 o 3.
        if (strlen($numero) === 12) {
            $largos = str_starts_with($numero, '11') ? [2] : (preg_match('/^[23]/', $numero) ? [3, 4] : []);
            foreach ($largos as $largo) {
                if (substr($numero, $largo, 2) === '15') {
                    return substr($numero, 0, $largo) . substr($numero, $largo + 2);
                }
            }
        }

        return $numero;
    }


    public function sendCommissionStatusNotification(
        string $phone,
        int $commissionId,
        string $newStatus,
        string $customerName,
        ?string $details = null,
        ?object $commission = null
    ): bool {
        $statusMessages = [
            'ACEPTADO' => '✅ Tu presupuesto ha sido ACEPTADO y tu envío está siendo preparado.',
            'RETIRADO' => '📦 Tu envío ha sido RETIRADO y está en camino hacia su destino.',
            'RETIRADO_SUCURSAL' => '📦 Tu envío ha sido RETIRADO EN SUCURSAL y está listo para ser retirado.',
            'ENTREGADO' => '🎉 ¡Tu envío ha sido ENTREGADO exitosamente!',
            'CANCELADO' => '❌ Tu envío ha sido CANCELADO.',
            'PAGADO' => '💰 El PAGO de tu envío ha sido confirmado.',
            'PAGO_CONFIRMADO' => '💰 El PAGO de tu envío ha sido confirmado.',
            'CADETE_ASIGNADO' => '👤 Se ha asignado un cadete a tu envío.',
            'ENCOMIENDA_RETIRADA' => '📦 Tu encomienda ha sido retirada y está en tránsito.',
        ];

        $statusMessage = $statusMessages[$newStatus] ?? "El estado de tu envío ha cambiado a: {$newStatus}";

        $trackingUrl = rtrim(config('app.frontend_url'), '/') . "/tracking/{$commissionId}";

        $message = "🚚 *RYR Comisiones*\n\n";
        $message .= "Hola {$customerName},\n\n";
        $message .= "{$statusMessage}\n\n";
        $message .= "📋 *Envío #{$commissionId}*\n\n";

        if ($details) {
            $message .= "📝 *Detalles:* {$details}\n\n";
        }

        $message .= "🔍 Puedes hacer seguimiento de tu envío desde nuestra web:\n";
        $message .= "{$trackingUrl}\n\n";
        $message .= "También puedes acceder a tu panel de cliente para ver todos tus envíos y gestiones.\n\n";
        $message .= "Gracias por confiar en RYR Comisiones! 🚛";

        return $this->sendMessage($phone, $message);
    }

    /**
     * Envía WhatsApp al cliente cuando se crea una comisión
     */
    public function sendCommissionCreatedNotification(
        string $phone,
        int $commissionId,
        string $customerName,
        ?object $commission = null
    ): bool {
        $trackingUrl = rtrim(config('app.frontend_url'), '/') . "/tracking/{$commissionId}";

        $message = "🚚 *RYR Comisiones*\n\n";
        $message .= "Hola {$customerName},\n\n";
        $message .= "🏁 Tu comisión fue cargada a nuestro sistema con éxito!.\n\n";
        $message .= "📋 *Envío #{$commissionId}*\n\n";

        // Agregar notas si existen
        if ($commission && isset($commission->notes) && ! empty(trim($commission->notes))) {
            $message .= "📝 *Mensaje:*\n";
            $message .= trim($commission->notes) . "\n\n";
        }

        $message .= "🔍 Puedes hacer seguimiento de tu envío desde nuestra web:\n";
        $message .= "{$trackingUrl}\n\n";
        $message .= "También puedes acceder a tu panel de cliente para ver todos tus envíos y gestiones.\n\n";
        $message .= "Gracias por confiar en RYR Comisiones! 🚛";

        return $this->sendMessage($phone, $message);
    }
}
