<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\Commissions\Infrastructure\Mappers\CommissionMapper;
use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Services\FcmNotificationService;
use App\Services\WhatsAppService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class CreateCommissionUseCase
{
    public function __construct(
        private CommissionsRepository $commissionRepository,
        private CustomerRepository    $customerRepository,
        private DestinationRepository $destinationRepository,
        private CurrentAccountRepository $currentAccountRepository,
        private FcmNotificationService $fcmNotificationService,
        private WhatsAppService $whatsAppService
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(CreateCommissionDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $this->validateCustomer($dto->clientId);
            $destination = $this->validateDestination($dto->origin, $dto->destination);
            if ($dto->items !== null) {
                $this->validateItems($dto->items);
            }
            $this->validateLocations($dto->originLocationId, $dto->destinationLocationId);

            $commission = $this->commissionRepository->create($dto, $destination->id);
            if ($dto->items !== null && !empty($dto->items)) {
                $this->commissionRepository->addItems($commission->id, $dto->items);
            }

            // Ya no creamos transacción en cuenta corriente al crear la comisión
            // Para comisiones ORDINARIAS: se crea cuando cambia a PAGO_VALIDACION
            // Para comisiones EXTRAORDINARIAS: no se crea nunca

            // Guardar el estado antes de crear el log
            $commissionStatus = $dto->status;

            $logDto = new CreateCommissionLogDTO(
                commissionId: $commission->id,
                userId: Auth::id(),
                previousStatus: "",
                newStatus: $commissionStatus->value,
                details: 'Comisión creada'
            );

            $this->commissionRepository->createLog($logDto);

            // Enviar WhatsApp al cliente cuando se crea la comisión
            $this->sendWhatsAppOnCommissionCreated($commission->id);

            // Si el estado es BUSCANDO_CADETE, notificar a todos los cadetes de la sucursal
            if ($commissionStatus === CommissionStatus::BUSCANDO_CADETE) {
                $commissionWithRelations = $this->commissionRepository->findById($commission->id);
                $this->notifyAllCadetesNewCommission($commissionWithRelations);
            }

            return CommissionMapper::fromEntityToArray($this->commissionRepository->findById($commission->id));
        });
    }

    /**
     * @throws \Exception
     */
    private function validateCustomer(int $customerId): void
    {
        if (! $this->customerRepository->findById($customerId)) {
            throw new \Exception('Cliente no encontrado');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateDestination(string $origin, string $destination): Destination
    {
        return $this->destinationRepository->findByOriginAndDestination($origin, $destination);
    }

    /**
     * @throws \Exception
     */
    private function validateItems(array $items): void
    {
        if (empty($items)) {
            throw new \Exception('Si se proporcionan items, la comisión debe contener al menos un item');
        }
    }

    /**
     * @throws \Exception
     */
    private function validateLocations(int $originLocationId, int $destinationLocationId): void
    {
        $originLocation = Location::find($originLocationId);
        if (! $originLocation) {
            throw new \Exception('La ubicación de origen no existe');
        }

        $destinationLocation = Location::find($destinationLocationId);
        if (! $destinationLocation) {
            throw new \Exception('La ubicación de destino no existe');
        }
    }

    /**
     * Crea una transacción en cuenta corriente por el monto de la comisión como saldo deudor
     */
    private function createCurrentAccountTransaction(CreateCommissionDTO $dto, int $commissionId): void
    {
        // Verificar si ya existe una transacción con esta referencia para evitar duplicados
        $reference = "COM-{$commissionId}";
        if (CurrentAccount::where('reference', $reference)->exists()) {
            Log::info('Transacción en cuenta corriente ya existe para esta comisión', [
                'commission_id' => $commissionId,
                'reference' => $reference,
            ]);
            return;
        }

        $currentAccountDTO = new CreateCurrentAccountDTO(
            customerId: $dto->clientId,
            type: 'debit', // Saldo negativo (deuda)
            amount: $dto->total,
            description: "Comisión #{$commissionId} - {$dto->origin} a {$dto->destination}",
            reference: $reference,
            transactionDate: $dto->date->format('Y-m-d'),
            paymentMethod: null,
            observations: "Comisión registrada a cuenta corriente como saldo deudor",
            userId: Auth::id(),
        );

        $this->currentAccountRepository->create($currentAccountDTO);
    }

    /**
     * Notifica a todos los cadetes de la sucursal que hay una nueva comisión disponible
     */
    private function notifyAllCadetesNewCommission($commission): void
    {
        try {
            // Cargar relaciones necesarias si no están cargadas
            if (!$commission->relationLoaded('originLocation')) {
                $commission->load('originLocation');
            }
            if (!$commission->relationLoaded('destinationLocation')) {
                $commission->load('destinationLocation');
            }

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

            Log::info('Notificaciones push enviadas a cadetes por nueva comisión disponible (creación)', [
                'commission_id' => $commission->id,
                'branch_id' => $commission->branch_id,
                'total_cadetes' => $result['total_users'] ?? 0,
                'successful' => $result['successful'] ?? 0,
                'failed' => $result['failed'] ?? 0,
            ]);
        } catch (\Exception $e) {
            // Log el error pero no fallar la transacción principal
            Log::error('Error al notificar cadetes sobre nueva comisión disponible (creación)', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envía WhatsApp al cliente cuando se crea una comisión
     */
    private function sendWhatsAppOnCommissionCreated(int $commissionId): void
    {
        try {
            // Obtener la comisión con todas las relaciones necesarias
            $commission = $this->commissionRepository->findById($commissionId);
            
            if (!$commission) {
                Log::warning('No se pudo enviar WhatsApp de creación: comisión no encontrada', [
                    'commission_id' => $commissionId,
                ]);
                return;
            }

            // Cargar relaciones necesarias
            if (!$commission->relationLoaded('client')) {
                $commission->load('client');
            }
            if (!$commission->relationLoaded('originLocation')) {
                $commission->load('originLocation');
            }
            if (!$commission->relationLoaded('destinationLocation')) {
                $commission->load('destinationLocation');
            }

            $customer = $commission->client;
            
            if (!$customer) {
                Log::warning('No se pudo enviar WhatsApp de creación: cliente no encontrado', [
                    'commission_id' => $commissionId,
                ]);
                return;
            }

            // Obtener teléfono del cliente
            $phone = $customer->mobile ?: $customer->phone;
            
            if (!$phone) {
                Log::info('No se envió WhatsApp de creación: cliente sin teléfono', [
                    'commission_id' => $commissionId,
                    'customer_id' => $customer->id,
                ]);
                return;
            }

            // Enviar WhatsApp usando el servicio existente
            $success = $this->whatsAppService->sendCommissionCreatedNotification(
                $phone,
                $commissionId,
                $customer->full_name,
                $commission
            );

            if ($success) {
                Log::info('WhatsApp de creación de comisión enviado', [
                    'commission_id' => $commissionId,
                    'customer_id' => $customer->id,
                    'phone' => $phone,
                ]);
            } else {
                Log::warning('No se pudo enviar WhatsApp de creación de comisión', [
                    'commission_id' => $commissionId,
                    'customer_id' => $customer->id,
                    'phone' => $phone,
                ]);
            }
        } catch (\Exception $e) {
            // Log el error pero no fallar la transacción principal
            Log::error('Error enviando WhatsApp de creación de comisión', [
                'commission_id' => $commissionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
