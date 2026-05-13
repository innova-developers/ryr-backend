<?php

namespace App\Services;

use App\Mail\CommissionStatusChangedMail;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommissionNotificationService
{
    public function __construct(
        private WhatsAppService $whatsAppService
    ) {
    }

    /**
     * Estados importantes que requieren notificación
     */
    private const IMPORTANT_STATUSES = [
        CommissionStatus::SOLICITUD_RECIBIDA,
        CommissionStatus::ENCOMIENDA_RETIRADA,
        CommissionStatus::RETIRADO_SUCURSAL,
        CommissionStatus::ENTREGADO,
    ];

    /**
     * Enviar notificaciones por email y WhatsApp cuando cambia el estado
     */
    public function notifyStatusChange(
        Commission $commission,
        string $previousStatus,
        CommissionStatus $newStatus,
        ?string $details = null
    ): void {
        // Solo notificar estados importantes
        if (! in_array($newStatus, self::IMPORTANT_STATUSES)) {
            return;
        }

        try {
            $customer = $commission->client;

            if (! $customer) {
                Log::warning('No se pudo notificar cambio de estado: cliente no encontrado', [
                    'commission_id' => $commission->id,
                ]);

                return;
            }

            // Enviar email si el cliente tiene email válido
            if ($customer->email && ! empty(trim($customer->email))) {
                $this->sendEmailNotification($commission, $customer, $previousStatus, $newStatus->value, $details);
            }

            // Enviar WhatsApp si el cliente tiene teléfono
            if ($customer->mobile || $customer->phone) {
                $phone = $customer->mobile ?: $customer->phone;
                $this->sendWhatsAppNotification($phone, $commission->id, $newStatus->value, $customer->full_name, $details, $commission);
            }

            Log::info('Notificaciones de cambio de estado enviadas', [
                'commission_id' => $commission->id,
                'customer_id' => $customer->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus->value,
                'email_sent' => ! empty($customer->email),
                'whatsapp_sent' => ! empty($phone),
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando notificaciones de cambio de estado', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar notificación por email
     */
    private function sendEmailNotification(
        Commission $commission,
        Customer $customer,
        string $previousStatus,
        string $newStatus,
        ?string $details = null
    ): void {
        try {
            Mail::to($customer->email)->send(
                new CommissionStatusChangedMail(
                    $commission,
                    $customer,
                    $previousStatus,
                    $newStatus,
                    $details
                )
            );

            Log::info('Email de cambio de estado enviado', [
                'commission_id' => $commission->id,
                'customer_email' => $customer->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando email de cambio de estado', [
                'commission_id' => $commission->id,
                'customer_email' => $customer->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enviar notificación por WhatsApp
     */
    private function sendWhatsAppNotification(
        string $phone,
        int $commissionId,
        string $newStatus,
        string $customerName,
        ?string $details = null,
        ?object $commission = null
    ): void {
        try {
            $success = $this->whatsAppService->sendCommissionStatusNotification(
                $phone,
                $commissionId,
                $newStatus,
                $customerName,
                $details,
                $commission
            );

            if ($success) {
                Log::info('WhatsApp de cambio de estado enviado', [
                    'commission_id' => $commissionId,
                    'phone' => $phone,
                ]);
            } else {
                Log::warning('No se pudo enviar WhatsApp de cambio de estado', [
                    'commission_id' => $commissionId,
                    'phone' => $phone,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error enviando WhatsApp de cambio de estado', [
                'commission_id' => $commissionId,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
