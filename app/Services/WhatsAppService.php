<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;
    private ?string $instanceId;
    private ?string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url', 'https://api.green-api.com');
        $this->instanceId = config('services.whatsapp.instance_id') ?? '';
        $this->token = config('services.whatsapp.token') ?? '';
    }

    public function sendMessage(string $phone, string $message): bool
    {
        try {
            // Validar que tenemos las credenciales necesarias
            if (empty($this->instanceId) || empty($this->token)) {
                Log::error('WhatsApp credentials not configured', [
                    'phone' => $phone,
                    'instance_id_set' => !empty($this->instanceId),
                    'token_set' => !empty($this->token),
                ]);
                return false;
            }

            $cleanPhone = $this->cleanPhoneNumber($phone);
            $chatId = $cleanPhone . '@c.us';

            // URL de GreenAPI: https://api.green-api.com/waInstance{instanceId}/sendMessage/{token}
            $url = "{$this->baseUrl}/waInstance{$this->instanceId}/sendMessage/{$this->token}";
            $payload = [
                'chatId' => $chatId,
                'message' => $message,
            ];

            Log::info('Attempting to send WhatsApp message via GreenAPI', [
                'phone' => $cleanPhone,
                'chat_id' => $chatId,
                'url' => $url,
                'base_url' => $this->baseUrl,
                'instance_id' => $this->instanceId,
            ]);

            $response = Http::timeout(30)->post($url, $payload);

            $responseData = $response->json();
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
                Log::error('Failed to send WhatsApp message via GreenAPI', [
                    'phone' => $cleanPhone,
                    'chat_id' => $chatId,
                    'response' => $responseData,
                    'status' => $statusCode,
                    'body' => $response->body(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message via GreenAPI', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function cleanPhoneNumber(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Si ya tiene el formato completo 549XXXXXXXXX, devolverlo tal como está
        if (str_starts_with($cleanPhone, '549')) {
            return $cleanPhone;
        }
        
        // Si tiene formato 54XXXXXXXXX, agregar el 9 después del 54
        if (str_starts_with($cleanPhone, '54')) {
            return '54' . '9' . substr($cleanPhone, 2);
        }
        
        // Si no tiene código de país, agregar 549 al principio
        return '549' . $cleanPhone;
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

        $trackingUrl = config('app.url') . "/tracking/{$commissionId}";

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
        $trackingUrl = config('app.url') . "/tracking/{$commissionId}";

        $message = "🚚 *RYR Comisiones*\n\n";
        $message .= "Hola {$customerName},\n\n";
        $message .= "🏁 Tu comisión fue cargada a nuestro sistema con éxito!.\n\n";
        $message .= "📋 *Envío #{$commissionId}*\n\n";

        // Agregar notas si existen
        if ($commission && isset($commission->notes) && !empty(trim($commission->notes))) {
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
