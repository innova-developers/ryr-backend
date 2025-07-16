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
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

readonly class CreateCommissionUseCase
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
    public function __invoke(CreateCommissionDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $this->validateCustomer($dto->clientId);
            $destination = $this->validateDestination($dto->origin, $dto->destination);
            $this->validateItems($dto->items);
            $this->validateLocations($dto->originLocationId, $dto->destinationLocationId);

            $commission = $this->commissionRepository->create($dto, $destination->id);
            $this->commissionRepository->addItems($commission->id, $dto->items);

            // Si a_cuenta es true, crear transacción en cuenta corriente
            if ($dto->aCuenta) {
                $this->createCurrentAccountTransaction($dto, $commission->id);
            }

            $dto = new CreateCommissionLogDTO(
                commissionId: $commission->id,
                userId: Auth::id(),
                previousStatus: "",
                newStatus: $dto->status->value,
                details: 'Comisión creada'
            );

            $this->commissionRepository->createLog($dto);

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
            throw new \Exception('La comisión debe contener al menos un item');
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
     * Crea una transacción en cuenta corriente por el monto de la comisión
     */
    private function createCurrentAccountTransaction(CreateCommissionDTO $dto, int $commissionId): void
    {
        $currentAccountDTO = new CreateCurrentAccountDTO(
            customerId: $dto->clientId,
            type: 'debit', // Saldo negativo (deuda)
            amount: $dto->total,
            description: "Comisión #{$commissionId} - {$dto->origin} a {$dto->destination}",
            reference: "COM-{$commissionId}",
            transactionDate: $dto->date->format('Y-m-d'),
            paymentMethod: null,
            observations: "Comisión registrada a cuenta corriente",
            userId: Auth::id(),
        );

        $this->currentAccountRepository->create($currentAccountDTO);
    }
}
