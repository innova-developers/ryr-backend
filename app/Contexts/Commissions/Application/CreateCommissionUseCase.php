<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\Commissions\Infrastructure\Mappers\CommissionMapper;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\Destination;
use App\Shared\Models\Rate;
use Illuminate\Support\Facades\DB;

readonly class CreateCommissionUseCase
{
    public function __construct(
        private CommissionsRepository $commissionRepository,
        private CustomerRepository    $customerRepository,
        private DestinationRepository $destinationRepository
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
            $commission = $this->commissionRepository->create($dto, $destination->id);
            $this->commissionRepository->addItems($commission->id, $dto->items);
            return CommissionMapper::fromEntityToArray($this->commissionRepository->findById($commission->id));
        });
    }

    /**
     * @throws \Exception
     */
    private function validateCustomer(int $customerId): void
    {
        if (!$this->customerRepository->findById($customerId)) {
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

}
