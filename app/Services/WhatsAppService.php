<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $baseUrl;
    private string $instanceId;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.base_url', 'https://api.green-api.com');
        $this->instanceId = config('services.whatsapp.instance_id');
        $this->token = config('services.whatsapp.token');
    }

    public function sendMessage(string $phone, string $message): bool
    {
        try {
            $cleanPhone = $this->cleanPhoneNumber($phone);

            /*$response = Http::post("{$this->baseUrl}/waInstance{$this->instanceId}/SendMessage/{$this->token}", [
                'chatId' => $cleanPhone . '@c.us',
                'message' => $message,
            ]);*/

            $response = Http::post("http://147.182.174.231/wa-whatsapp-demo1/send", [
                'to' => $cleanPhone . '@c.us',
                'message' => $message,
            ]);

            if ($response->successful()) {
                Log::info('WhatsApp message sent successfully', [
                    'phone' => $cleanPhone,
                    'response' => $response->json(),
                ]);

                return true;
            } else {
                Log::error('Failed to send WhatsApp message', [
                    'phone' => $cleanPhone,
                    'response' => $response->json(),
                    'status' => $response->status(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp message', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function cleanPhoneNumber(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleanPhone, '549')) {
            return $cleanPhone;
        }
        if (str_starts_with($cleanPhone, '54')) {
            return '9' . $cleanPhone;
        }
        return '549'.$cleanPhone;
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
            'ENTREGADO' => '🎉 ¡Tu envío ha sido ENTREGADO exitosamente!',
            'CANCELADO' => '❌ Tu envío ha sido CANCELADO.',
            'PAGADO' => '💰 El PAGO de tu envío ha sido confirmado.',
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

        // Agregar información de ubicaciones si está disponible
        if ($commission && $commission->originLocation && $commission->destinationLocation) {
            $message .= "📍 *Ubicaciones:*\n\n";
            $message .= "*Origen:*\n";
            $message .= "• Nombre: {$commission->originLocation->name}\n";
            $message .= "• Dirección: {$commission->originLocation->address}\n";
            $message .= "• Ciudad: {$commission->originLocation->origin}\n";
            $message .= "• Teléfono: {$commission->originLocation->phone}\n";
            $message .= "• Horario: {$commission->originLocation->schedule}\n\n";

            $message .= "*Destino:*\n";
            $message .= "• Nombre: {$commission->destinationLocation->name}\n";
            $message .= "• Dirección: {$commission->destinationLocation->address}\n";
            $message .= "• Ciudad: {$commission->destinationLocation->origin}\n";
            $message .= "• Teléfono: {$commission->destinationLocation->phone}\n";
            $message .= "• Horario: {$commission->destinationLocation->schedule}\n\n";
        }

        $message .= "🔍 Puedes hacer seguimiento de tu envío desde nuestra web:\n";
        $message .= "{$trackingUrl}\n\n";
        $message .= "También puedes acceder a tu panel de cliente para ver todos tus envíos y gestiones.\n\n";
        $message .= "Gracias por confiar en RYR Comisiones! 🚛";

        return $this->sendMessage($phone, $message);
    }
}
