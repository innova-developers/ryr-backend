<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Services\CommissionCustodyService;
use App\Services\CommissionNotificationService;
use App\Services\FcmNotificationService;
use App\Services\FeedbackService;
use App\Services\MatrixCommissionService;
use App\Services\NotificationService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Models\CurrentAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateCommissionStatusUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionsRepository,
        private readonly CurrentAccountRepository $currentAccountRepository,
        private readonly CommissionNotificationService $notificationService,
        private readonly NotificationService $pushNotificationService,
        private readonly FcmNotificationService $fcmNotificationService
    ) {}

    /**
     * @throws \Exception
     */
    public function __invoke(int $id, CommissionStatus $status, ?string $details = null, bool $aCuenta = false): void
    {
        DB::transaction(function () use ($id, $status, $details) {
            try {
                $commission = $this->commissionsRepository->findById($id);
                $previousStatus = $commission->status;

                // Guardar el tipo antes de actualizar
                $commissionType = $commission->type;

                // Manejar el flujo según el nuevo estado
                if ($status === CommissionStatus::PENDIENTE_PAGO) {
                    // Actualizar estado a PENDIENTE_PAGO
                    $this->commissionsRepository->updateStatus($id, CommissionStatus::PENDIENTE_PAGO);

                    // Crear log de cambio a PENDIENTE_PAGO
                    $logDto = new CreateCommissionLogDTO(
                        commissionId: $id,
                        userId: Auth::id() ?? 1,
                        previousStatus: $previousStatus->value,
                        newStatus: CommissionStatus::PENDIENTE_PAGO->value,
                        details: $details ?? 'Estado actualizado a PENDIENTE_PAGO'
                    );
                    $this->commissionsRepository->createLog($logDto);

                    // Si es ORDINARIA: automáticamente cambiar a PAGO_VALIDACION y crear movimiento
                    if ($commissionType === CommissionType::ORDINARIA) {
                        // Actualizar estado a PAGO_VALIDACION
                        $this->commissionsRepository->updateStatus($id, CommissionStatus::PAGO_VALIDACION);

                        // Refrescar la comisión para obtener datos actualizados
                        $commission->refresh();

                        // Crear log del cambio automático a PAGO_VALIDACION
                        $logDtoAuto = new CreateCommissionLogDTO(
                            commissionId: $id,
                            userId: Auth::id() ?? 1,
                            previousStatus: CommissionStatus::PENDIENTE_PAGO->value,
                            newStatus: CommissionStatus::PAGO_VALIDACION->value,
                            details: 'Cambio automático a PAGO_VALIDACION (comisión ordinaria)'
                        );
                        $this->commissionsRepository->createLog($logDtoAuto);

                        // Crear movimiento en cuenta corriente
                        $this->createCurrentAccountTransaction($commission);
                    }
                    // Si es EXTRAORDINARIA: solo queda en PENDIENTE_PAGO (sin crear movimiento)
                } else {
                    // Para otros estados (incluyendo PAGO_VALIDACION)
                    $this->commissionsRepository->updateStatus($id, $status);

                    // Crear transacción en cuenta corriente cuando el estado es PAGO_VALIDACION
                    // (por default solo llegamos a este caso si la comisión es extraordinaria,
                    // ya que las ordinarias se procesan automáticamente arriba)
                    if ($status === CommissionStatus::PAGO_VALIDACION) {
                        // Refrescar la comisión para obtener datos actualizados
                        $commission->refresh();
                        $this->createCurrentAccountTransaction($commission);
                    }

                    // RC-512/RC-516: sólo había rama de alta. Al retroceder desde
                    // PAGO_VALIDACION el débito quedaba vivo y el cliente seguía
                    // debiendo una comisión que ya no estaba facturada. Es el mismo
                    // agujero que el del borrado, por la otra puerta.
                    if ($previousStatus === CommissionStatus::PAGO_VALIDACION
                        && $status !== CommissionStatus::PAGO_VALIDACION) {
                        $this->deleteCurrentAccountTransaction($id);
                    }

                    // Crear log del cambio de estado
                    $dto = new CreateCommissionLogDTO(
                        commissionId: $id,
                        userId: Auth::id() ?? 1,
                        previousStatus: $previousStatus->value,
                        newStatus: $status->value,
                        details: $details
                    );
                    $this->commissionsRepository->createLog($dto);
                }

                // Refrescar la comisión para asegurar que tenemos el estado final correcto
                $commission->refresh();
                $finalStatus = $commission->status;

                // RC-482: la liquidación se le acredita al cadete que REALIZÓ EL RETIRO,
                // no al primero que se asignó la comisión. Este es el único punto por el
                // que pasan todos los cambios de estado, así que es donde se define.
                if ($finalStatus === CommissionStatus::ENCOMIENDA_RETIRADA) {
                    $actorId = Auth::id() ?? $commission->cadete_id;

                    if ($actorId) {
                        app(CommissionCustodyService::class)->confirmPickup($commission, (int) $actorId);
                        $commission->save();
                    }
                }

                // Si el estado es INTENTO_ENTREGA_FALLIDO, crear nueva comisión con precio base
                if ($finalStatus === CommissionStatus::INTENTO_ENTREGA_FALLIDO) {
                    $this->createFailedDeliveryCommission($commission);
                }

                // Si PAGO_CONFIRMADO y tiene franchise, crear receivable para matriz
                if ($finalStatus === CommissionStatus::PAGO_CONFIRMADO && $commission->franchise_id) {
                    try {
                        $matrixService = new MatrixCommissionService;
                        $matrixService->createReceivableForCommission($commission);
                    } catch (\Exception $e) {
                        Log::error('Error creando receivable de matriz', [
                            'commission_id' => $commission->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Si ENTREGADO, crear encuesta de feedback
                if ($finalStatus === CommissionStatus::ENTREGADO) {
                    try {
                        $feedbackService = app(FeedbackService::class);
                        $feedbackService->createSurveyForCommission($commission);
                    } catch (\Exception $e) {
                        Log::error('Error creando encuesta de feedback', [
                            'commission_id' => $commission->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Crear notificación push para el cadete
                $this->pushNotificationService->createCommissionStatusNotification(
                    $commission,
                    $previousStatus->value,
                    $finalStatus,
                    $details
                );

                // Si el estado cambió a BUSCANDO_CADETE, notificar a todos los cadetes
                if ($finalStatus === CommissionStatus::BUSCANDO_CADETE) {
                    $this->notifyAllCadetesNewCommission($commission);
                }
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        });
    }

    /**
     * Da de baja el movimiento de cuenta corriente de la comisión.
     *
     * Va por el repositorio porque ese camino recalcula los saldos del cliente. El
     * guard anti-duplicados de createCurrentAccountTransaction() usa exists(), que
     * respeta el soft delete, así que si la comisión vuelve a avanzar a
     * PAGO_VALIDACION el movimiento se crea de nuevo sin conflicto.
     */
    private function deleteCurrentAccountTransaction(int $commissionId): void
    {
        $movimiento = CurrentAccount::where('reference', "COM-{$commissionId}")->first();

        if (! $movimiento) {
            return;
        }

        $this->currentAccountRepository->delete($movimiento->id);

        Log::info('Movimiento de cuenta corriente dado de baja por retroceso de estado', [
            'commission_id' => $commissionId,
            'current_account_id' => $movimiento->id,
            'amount' => $movimiento->amount,
        ]);
    }

    /**
     * Crea una transacción en cuenta corriente por el monto de la comisión como saldo deudor
     */
    private function createCurrentAccountTransaction($commission): void
    {
        // Verificar si ya existe una transacción con esta referencia para evitar duplicados
        $reference = "COM-{$commission->id}";
        if (CurrentAccount::where('reference', $reference)->exists()) {
            Log::info('Transacción en cuenta corriente ya existe para esta comisión', [
                'commission_id' => $commission->id,
                'reference' => $reference,
            ]);

            return;
        }

        // Asegurar que la relación destination esté cargada
        if (! $commission->relationLoaded('destination')) {
            $commission->load('destination');
        }

        $origin = $commission->destination ? $commission->destination->origin : 'Origen';
        $destination = $commission->destination ? $commission->destination->destination : 'Destino';

        $currentAccountDTO = new CreateCurrentAccountDTO(
            customerId: $commission->client_id,
            type: 'debit', // Saldo negativo (deuda)
            amount: $commission->total,
            description: "Comisión #{$commission->id} - {$origin} a {$destination}",
            reference: $reference,
            transactionDate: $commission->date->format('Y-m-d'),
            paymentMethod: null,
            observations: 'Comisión registrada a cuenta corriente como saldo deudor',
            userId: Auth::id() ?? 1, // Usar ID 1 como fallback si no hay usuario autenticado
        );

        $this->currentAccountRepository->create($currentAccountDTO);
    }

    /**
     * Crea una nueva comisión con precio base cuando la entrega falla
     */
    private function createFailedDeliveryCommission($originalCommission): void
    {
        try {
            // Obtener el precio base del destino
            $destination = $originalCommission->destination;
            $basePrice = $destination->fixed_price;

            // Crear DTO para la nueva comisión
            $newCommissionDTO = new CreateCommissionDTO(
                clientId: $originalCommission->client_id,
                date: now(),
                origin: $originalCommission->destination->origin,
                destination: $originalCommission->destination->destination,
                status: CommissionStatus::SOLICITUD_RECIBIDA,
                items: null, // No items para comisión de entrega fallida
                total: $basePrice,
                originLocationId: $originalCommission->origin_location_id,
                destinationLocationId: $originalCommission->destination_location_id,
                notes: "Comisión creada automáticamente por entrega fallida de comisión #{$originalCommission->id}",
                aCuenta: false
            );

            // Crear la nueva comisión
            $newCommission = $this->commissionsRepository->create($newCommissionDTO, $originalCommission->destination_id);

            // Crear log para la nueva comisión
            $logDTO = new CreateCommissionLogDTO(
                commissionId: $newCommission->id,
                userId: Auth::id() ?? 1, // Usar ID 1 como fallback si no hay usuario autenticado
                previousStatus: '',
                newStatus: CommissionStatus::SOLICITUD_RECIBIDA->value,
                details: "Comisión creada automáticamente por entrega fallida de comisión #{$originalCommission->id}"
            );

            $this->commissionsRepository->createLog($logDTO);

        } catch (\Exception $e) {
            // Log el error pero no fallar la transacción principal
            \Log::error('Error al crear comisión por entrega fallida: '.$e->getMessage());
        }
    }

    /**
     * Notifica a todos los cadetes que hay una nueva comisión disponible en el pool
     */
    private function notifyAllCadetesNewCommission($commission): void
    {
        try {
            // Preparar datos de la notificación
            $origin = $commission->originLocation ? $commission->originLocation->name : 'Origen';
            $destination = $commission->destinationLocation ? $commission->destinationLocation->name : 'Destino';

            $payload = [
                'title' => 'Nueva comisión disponible',
                'body' => "Nueva comisión #{$commission->id} disponible: {$origin} → {$destination}",
                'data' => [
                    'type' => 'new_commission_available',
                    'commission_id' => $commission->id,
                    'status' => $commission->status->value,
                    'origin' => $origin,
                    'destination' => $destination,
                    'total' => $commission->total,
                ],
            ];

            // Enviar notificación a todos los cadetes de la sucursal con tokens FCM activos
            $result = $this->fcmNotificationService->sendPushToAllCadetes($payload, $commission->branch_id);

            \Log::info('Notificaciones push enviadas a cadetes por nueva comisión disponible', [
                'commission_id' => $commission->id,
                'branch_id' => $commission->branch_id,
                'total_cadetes' => $result['total_users'] ?? 0,
                'successful' => $result['successful'] ?? 0,
                'failed' => $result['failed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            // Log el error pero no fallar la transacción principal
            \Log::error('Error al notificar cadetes sobre nueva comisión disponible', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
