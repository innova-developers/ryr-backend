<?php

namespace App\Services;

use App\Notification;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Estados que requieren notificación al cadete
     */
    private const CADETE_NOTIFICATION_STATUSES = [
        CommissionStatus::CADETE_ASIGNADO,
        CommissionStatus::CADETE_EN_CAMINO_ORIGEN,
        CommissionStatus::EN_PUNTO_RETIRO,
        CommissionStatus::ENCOMIENDA_RETIRADA,
        CommissionStatus::EN_CAMINO_PLANTA,
        CommissionStatus::EN_TRANSITO_DESTINO,
        CommissionStatus::EN_PROCESO_ENTREGA,
        CommissionStatus::ENTREGADO,
        CommissionStatus::RETIRADO_SUCURSAL,
        CommissionStatus::INTENTO_ENTREGA_FALLIDO,
        CommissionStatus::INTENTO_RETIRO_FALLIDO,
        CommissionStatus::REPROGRAMANDO_ENTREGA,
        CommissionStatus::DISPONIBLE_RETIRO,
        CommissionStatus::EN_DEVOLUCION,
        CommissionStatus::DEVUELTO_REMITENTE,
    ];

    /**
     * Estados que requieren notificación push FCM
     */
    private const FCM_PUSH_STATUSES = [
        CommissionStatus::CADETE_ASIGNADO,
        CommissionStatus::ENTREGADO,
        CommissionStatus::CANCELADO,
    ];

    private ?FcmNotificationService $fcmService = null;

    public function __construct(?FcmNotificationService $fcmService = null)
    {
        // Inyectar FcmNotificationService si está disponible
        $this->fcmService = $fcmService ?? app(FcmNotificationService::class);
    }

    /**
     * Crear notificación para cambio de estado de comisión
     */
    public function createCommissionStatusNotification(
        Commission $commission,
        string $previousStatus,
        CommissionStatus $newStatus,
        ?string $details = null
    ): ?Notification {
        // Solo crear notificación si el estado requiere notificación al cadete
        if (! in_array($newStatus, self::CADETE_NOTIFICATION_STATUSES)) {
            return null;
        }

        // Solo notificar si hay un cadete asignado
        if (! $commission->cadete_id) {
            return null;
        }

        try {
            $title = $this->getNotificationTitle($newStatus);
            $message = $this->getNotificationMessage($commission, $newStatus, $details);
            $data = $this->getNotificationData($commission, $previousStatus, $newStatus, $details);

            $notification = Notification::create([
                'user_id' => $commission->cadete_id,
                'commission_id' => $commission->id,
                'type' => 'commission_status_change',
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

            // Enviar notificación push FCM si el estado lo requiere
            if (in_array($newStatus, self::FCM_PUSH_STATUSES)) {
                $this->sendFcmPushNotification($commission->cadete_id, $title, $message, $data);
            }

            return $notification;

        } catch (\Exception $e) {
            Log::error('Error creando notificación de cambio de estado', [
                'commission_id' => $commission->id,
                'cadete_id' => $commission->cadete_id,
                'new_status' => $newStatus->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crear notificación cuando se asigna un cadete a una comisión
     */
    public function createCommissionAssignedNotification(Commission $commission): ?Notification
    {
        if (! $commission->cadete_id) {
            return null;
        }

        try {
            $title = 'Nueva comisión asignada';
            $message = "Se te ha asignado una nueva comisión #{$commission->id}";
            $data = [
                'commission_id' => $commission->id,
                'status' => $commission->status->value,
                'client_name' => $commission->client->full_name ?? 'Cliente',
                'total' => $commission->total,
                'origin' => $commission->originLocation->name ?? 'Origen',
                'destination' => $commission->destinationLocation->name ?? 'Destino',
            ];

            return Notification::create([
                'user_id' => $commission->cadete_id,
                'commission_id' => $commission->id,
                'type' => 'commission_assigned',
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Error creando notificación de comisión asignada', [
                'commission_id' => $commission->id,
                'cadete_id' => $commission->cadete_id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Obtener notificaciones de un usuario
     */
    public function getUserNotifications(int $userId, int $limit = 50, int $offset = 0): array
    {
        return Notification::where('user_id', $userId)
            ->with(['commission.client', 'commission.originLocation', 'commission.destinationLocation'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->toArray();
    }

    /**
     * Obtener notificaciones no leídas de un usuario
     */
    public function getUnreadNotifications(int $userId): array
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->with(['commission.client', 'commission.originLocation', 'commission.destinationLocation'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Marcar notificación como leída
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    /**
     * Marcar todas las notificaciones de un usuario como leídas
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Obtener título de la notificación según el estado
     */
    private function getNotificationTitle(CommissionStatus $status): string
    {
        return match($status) {
            CommissionStatus::CADETE_ASIGNADO => 'Nueva comisión asignada',
            CommissionStatus::CADETE_EN_CAMINO_ORIGEN => 'En camino al origen',
            CommissionStatus::EN_PUNTO_RETIRO => 'En punto de retiro',
            CommissionStatus::ENCOMIENDA_RETIRADA => 'Encomienda retirada',
            CommissionStatus::EN_CAMINO_PLANTA => 'En camino a planta/sucursal',
            CommissionStatus::EN_TRANSITO_DESTINO => 'En tránsito a destino',
            CommissionStatus::EN_PROCESO_ENTREGA => 'En proceso de entrega',
            CommissionStatus::ENTREGADO => 'Comisión entregada',
            CommissionStatus::RETIRADO_SUCURSAL => 'Retirado en sucursal',
            CommissionStatus::INTENTO_ENTREGA_FALLIDO => 'Intento de entrega fallido',
            CommissionStatus::INTENTO_RETIRO_FALLIDO => 'Intento de retiro fallido',
            CommissionStatus::REPROGRAMANDO_ENTREGA => 'Reprogramando entrega',
            CommissionStatus::DISPONIBLE_RETIRO => 'Disponible para retiro',
            CommissionStatus::EN_DEVOLUCION => 'En devolución',
            CommissionStatus::DEVUELTO_REMITENTE => 'Devuelto al remitente',
            default => 'Actualización de comisión',
        };
    }

    /**
     * Obtener mensaje de la notificación
     */
    private function getNotificationMessage(Commission $commission, CommissionStatus $status, ?string $details = null): string
    {
        $baseMessage = "Comisión #{$commission->id}: {$status->getCadeteStatus()}";

        if ($details) {
            $baseMessage .= " - {$details}";
        }

        return $baseMessage;
    }

    /**
     * Obtener datos adicionales de la notificación
     */
    private function getNotificationData(
        Commission $commission,
        string $previousStatus,
        CommissionStatus $newStatus,
        ?string $details = null
    ): array {
        return [
            'commission_id' => $commission->id,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus->value,
            'status_label' => $newStatus->getCadeteStatus(),
            'client_name' => $commission->client->full_name ?? 'Cliente',
            'total' => $commission->total,
            'origin' => $commission->originLocation->name ?? 'Origen',
            'destination' => $commission->destinationLocation->name ?? 'Destino',
            'details' => $details,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Enviar notificación push FCM al cadete
     */
    private function sendFcmPushNotification(int $userId, string $title, string $body, array $data): void
    {
        try {
            if (! $this->fcmService) {
                Log::warning('FcmNotificationService no disponible para enviar push notification', [
                    'user_id' => $userId,
                ]);

                return;
            }

            $result = $this->fcmService->sendPushToUser($userId, [
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            Log::info('Notificación push FCM enviada', [
                'user_id' => $userId,
                'sent' => $result['sent'] ?? 0,
                'failed' => $result['failed'] ?? 0,
            ]);

        } catch (\Exception $e) {
            // No fallar la creación de la notificación si falla el push
            Log::error('Error enviando notificación push FCM', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
