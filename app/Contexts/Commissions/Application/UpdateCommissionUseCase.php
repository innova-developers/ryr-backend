<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\UpdateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Application\DTOs\CommissionItemDTO;
use App\Contexts\Commissions\Infrastructure\Mappers\CommissionMapper;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Enums\CommissionItemSize;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class UpdateCommissionUseCase
{
    public function __construct(
        private CommissionsRepository $commissionRepository,
        private CustomerRepository    $customerRepository,
        private DestinationRepository $destinationRepository,
        private CurrentAccountRepository $currentAccountRepository
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(UpdateCommissionDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            // Verificar que la comisión existe
            $existingCommission = $this->commissionRepository->findById($dto->id);
            
            $this->validateCustomer($dto->clientId);
            $destination = $this->validateDestination($dto->origin, $dto->destination);
            if ($dto->items !== null) {
                $this->validateItems($dto->items);
            }
            $this->validateLocations($dto->originLocationId, $dto->destinationLocationId);

            // Recalcular precios de items si faltan valores (cuando viene desde la app de cadetes)
            $recalculatedItems = $this->recalculateItemsPrices($dto->items, $destination);

            // Recalcular el total: fixed_price de destination + suma de subtotales de items
            $itemsTotal = 0.0;
            if ($recalculatedItems !== null && !empty($recalculatedItems)) {
                foreach ($recalculatedItems as $item) {
                    $itemsTotal += $item->subtotal;
                }
            }
            $recalculatedTotal = $destination->fixed_price + $itemsTotal;

            // Crear un nuevo DTO con el total recalculado y los items con precios recalculados
            $updatedDto = new UpdateCommissionDTO(
                id: $dto->id,
                clientId: $dto->clientId,
                date: $dto->date,
                origin: $dto->origin,
                destination: $dto->destination,
                status: $dto->status,
                items: $recalculatedItems,
                total: $recalculatedTotal,
                originLocationId: $dto->originLocationId,
                destinationLocationId: $dto->destinationLocationId,
                notes: $dto->notes,
                aCuenta: $dto->aCuenta,
                type: $dto->type
            );

            // Actualizar la comisión con el total recalculado
            $this->commissionRepository->update($updatedDto, $destination->id);
            
            // Eliminar items existentes y agregar los nuevos (con precios recalculados)
            $this->commissionRepository->deleteItems($dto->id);
            if ($recalculatedItems !== null && !empty($recalculatedItems)) {
                $this->commissionRepository->addItems($dto->id, $recalculatedItems);
            }

            // Actualizar el movimiento en cuenta corriente si existe (cuando se recalcula el total)
            $this->updateCurrentAccountTransaction($dto->id, $recalculatedTotal, $dto->origin, $dto->destination);

            // Obtener el tipo de comisión antes de procesar el estado
            $commissionType = $existingCommission->type;

            // Manejar el flujo según el nuevo estado
            if ($dto->status === CommissionStatus::PENDIENTE_PAGO) {
                // Crear log de cambio a PENDIENTE_PAGO
                $logDto = new CreateCommissionLogDTO(
                    commissionId: $dto->id,
                    userId: Auth::id(),
                    previousStatus: $existingCommission->status->value,
                    newStatus: CommissionStatus::PENDIENTE_PAGO->value,
                    details: 'Comisión actualizada'
                );
                $this->commissionRepository->createLog($logDto);

                // Si es ORDINARIA: automáticamente cambiar a PAGO_VALIDACION y crear movimiento
                if ($commissionType === CommissionType::ORDINARIA) {
                    // Actualizar estado a PAGO_VALIDACION
                    $this->commissionRepository->updateStatus($dto->id, CommissionStatus::PAGO_VALIDACION);
                    
                    // Crear log del cambio automático a PAGO_VALIDACION
                    $logDtoAuto = new CreateCommissionLogDTO(
                        commissionId: $dto->id,
                        userId: Auth::id(),
                        previousStatus: CommissionStatus::PENDIENTE_PAGO->value,
                        newStatus: CommissionStatus::PAGO_VALIDACION->value,
                        details: 'Cambio automático a PAGO_VALIDACION (comisión ordinaria)'
                    );
                    $this->commissionRepository->createLog($logDtoAuto);

                    // Obtener la comisión actualizada para crear el movimiento
                    $updatedCommission = $this->commissionRepository->findById($dto->id);
                    // Crear movimiento en cuenta corriente
                    $this->createCurrentAccountTransaction($updatedDto, $updatedCommission);
                }
                // Si es EXTRAORDINARIA: solo queda en PENDIENTE_PAGO (sin crear movimiento)
            } else {
                // Para otros estados, crear log normal
            $logDto = new CreateCommissionLogDTO(
                commissionId: $dto->id,
                userId: Auth::id(),
                previousStatus: $existingCommission->status->value,
                newStatus: $dto->status->value,
                details: 'Comisión actualizada'
            );
            $this->commissionRepository->createLog($logDto);
            }

            return CommissionMapper::fromEntityToArray($this->commissionRepository->findById($dto->id));
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
    private function validateDestination(string $origin, string $destination): \App\Shared\Models\Destination
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
    private function createCurrentAccountTransaction(UpdateCommissionDTO $dto, $commission): void
    {
        // Verificar si ya existe una transacción con esta referencia para evitar duplicados
        $reference = "COM-{$dto->id}";
        if (CurrentAccount::where('reference', $reference)->exists()) {
            Log::info('Transacción en cuenta corriente ya existe para esta comisión', [
                'commission_id' => $dto->id,
                'reference' => $reference,
            ]);
            return;
        }

        // Asegurar que la relación destination esté cargada
        if (!$commission->relationLoaded('destination')) {
            $commission->load('destination');
        }

        $origin = $commission->destination ? $commission->destination->origin : $dto->origin;
        $destination = $commission->destination ? $commission->destination->destination : $dto->destination;

        $currentAccountDTO = new CreateCurrentAccountDTO(
            customerId: $dto->clientId,
            type: 'debit', // Saldo negativo (deuda)
            amount: $dto->total,
            description: "Comisión #{$dto->id} - {$origin} a {$destination}",
            reference: $reference,
            transactionDate: $dto->date->format('Y-m-d'),
            paymentMethod: null,
            observations: "Comisión registrada a cuenta corriente como saldo deudor",
            userId: Auth::id(),
        );

        $this->currentAccountRepository->create($currentAccountDTO);
    }

    /**
     * Actualiza el movimiento en cuenta corriente asociado a una comisión cuando se recalcula el total
     */
    private function updateCurrentAccountTransaction(int $commissionId, float $newTotal, string $origin, string $destination): void
    {
        $reference = "COM-{$commissionId}";
        $currentAccountTransaction = CurrentAccount::where('reference', $reference)->first();

        if ($currentAccountTransaction) {
            // Actualizar el monto y la descripción con el nuevo total
            $updateDTO = new UpdateCurrentAccountDTO(
                id: $currentAccountTransaction->id,
                type: null,
                amount: $newTotal,
                description: "Comisión #{$commissionId} - {$origin} a {$destination}",
                reference: null,
                transactionDate: null,
                paymentMethod: null,
                observations: null,
            );

            $this->currentAccountRepository->update($updateDTO);

            Log::info('Movimiento en cuenta corriente actualizado por recálculo de total', [
                'commission_id' => $commissionId,
                'reference' => $reference,
                'new_total' => $newTotal,
            ]);
        }
    }

    /**
     * Recalcula los precios de los items cuando faltan valores (unit_price o subtotal = 0)
     * Esto ocurre cuando la actualización viene desde la app de cadetes que no tiene acceso a los precios
     */
    private function recalculateItemsPrices(?array $items, \App\Shared\Models\Destination $destination): ?array
    {
        if ($items === null || empty($items)) {
            return $items;
        }

        $recalculatedItems = [];
        foreach ($items as $item) {
            $unitPrice = $item->unitPrice;
            $subtotal = $item->subtotal;

            // Si unit_price o subtotal son 0 o nulos, recalcular usando los precios del destination
            if ($unitPrice <= 0 || $subtotal <= 0) {
                // Solo recalcular si el item tiene tamaño (ORDINARIA)
                if ($item->size !== null) {
                    // Determinar el precio unitario según el tamaño
                    if ($item->size === CommissionItemSize::SMALL) {
                        $unitPrice = $destination->small_bulk_price;
                    } elseif ($item->size === CommissionItemSize::LARGE) {
                        $unitPrice = $destination->large_bulk_price;
                    } else {
                        // Si no es CHICO ni GRANDE, mantener el precio original o usar un valor por defecto
                        $unitPrice = $unitPrice > 0 ? $unitPrice : 0;
                    }

                    // Recalcular el subtotal
                    $subtotal = $unitPrice * $item->quantity;
                } else {
                    // Para items EXTRAORDINARIOS sin tamaño, mantener los valores originales
                    // Si ambos son 0, mantenerlos en 0
                    $unitPrice = $unitPrice > 0 ? $unitPrice : 0;
                    $subtotal = $subtotal > 0 ? $subtotal : 0;
                }
            }

            // Crear un nuevo DTO con los valores recalculados
            $recalculatedItems[] = new CommissionItemDTO(
                id: $item->id,
                commissionId: $item->commissionId,
                type: $item->type,
                size: $item->size,
                quantity: $item->quantity,
                unitPrice: $unitPrice,
                subtotal: $subtotal,
                detail: $item->detail,
                createdAt: $item->createdAt,
                updatedAt: $item->updatedAt,
                deletedAt: $item->deletedAt
            );
        }

        return $recalculatedItems;
    }
}
