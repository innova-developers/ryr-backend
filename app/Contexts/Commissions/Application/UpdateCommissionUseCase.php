<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\UpdateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Infrastructure\Mappers\CommissionMapper;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
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

            // Recalcular el total: fixed_price de destination + suma de subtotales de items
            $itemsTotal = 0.0;
            if ($dto->items !== null && !empty($dto->items)) {
                foreach ($dto->items as $item) {
                    $itemsTotal += $item->subtotal;
                }
            }
            $recalculatedTotal = $destination->fixed_price + $itemsTotal;

            // Crear un nuevo DTO con el total recalculado
            $updatedDto = new UpdateCommissionDTO(
                id: $dto->id,
                clientId: $dto->clientId,
                date: $dto->date,
                origin: $dto->origin,
                destination: $dto->destination,
                status: $dto->status,
                items: $dto->items,
                total: $recalculatedTotal,
                originLocationId: $dto->originLocationId,
                destinationLocationId: $dto->destinationLocationId,
                notes: $dto->notes,
                aCuenta: $dto->aCuenta
            );

            // Actualizar la comisión con el total recalculado
            $this->commissionRepository->update($updatedDto, $destination->id);
            
            // Eliminar items existentes y agregar los nuevos
            $this->commissionRepository->deleteItems($dto->id);
            if ($dto->items !== null && !empty($dto->items)) {
                $this->commissionRepository->addItems($dto->id, $dto->items);
            }

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
}
