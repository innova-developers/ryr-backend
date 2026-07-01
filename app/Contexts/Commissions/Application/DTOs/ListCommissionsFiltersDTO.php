<?php

namespace App\Contexts\Commissions\Application\DTOs;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Enums\PaymentMethod;

class ListCommissionsFiltersDTO
{
    public function __construct(
        public readonly ?int $commissionId = null,
        public readonly ?int $clientId = null,
        public readonly ?string $client = null,
        public readonly ?string $customerCity = null,
        public readonly ?int $destinationId = null,
        public readonly ?int $branchId = null,
        public readonly ?int $userId = null,
        public readonly ?int $cadeteId = null,
        public readonly ?int $originLocationId = null,
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly ?CommissionStatus $status = null,
        public readonly ?PaymentMethod $method = null,
        public readonly ?CommissionType $type = null,
        public readonly ?float $total = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $sort = 'pickup_first',
        public readonly ?string $sortDirection = 'asc',
    ) {
    }

    public static function fromArray(array $data): self
    {
        // Normalizar método de pago a mayúsculas para asegurar compatibilidad con el enum
        $method = null;
        if (isset($data['method']) && ! empty($data['method'])) {
            try {
                $methodValue = strtoupper(trim($data['method']));
                $method = PaymentMethod::from($methodValue);
            } catch (\ValueError $e) {
                // Si el valor no es válido, intentar buscar por coincidencia parcial
                $methodValue = strtoupper(trim($data['method']));
                foreach (PaymentMethod::cases() as $case) {
                    if ($case->value === $methodValue) {
                        $method = $case;

                        break;
                    }
                }
                // Si aún no se encuentra, dejar como null
            }
        }

        return new self(
            commissionId: $data['commissionId'] ?? null,
            clientId: $data['client_id'] ?? null,
            client: $data['clientName'] ?? null,
            customerCity: $data['customerCity'] ?? $data['customer_city'] ?? null,
            destinationId: $data['destination_id'] ?? null,
            branchId: $data['branch_id'] ?? null,
            userId: $data['user_id'] ?? null,
            cadeteId: $data['cadete_id'] ?? $data['cadeteId'] ?? null,
            originLocationId: $data['origin_location_id'] ?? $data['originLocationId'] ?? null,
            dateFrom: $data['date_from'] ?? $data['dateFrom'] ?? null,
            dateTo: $data['date_to'] ?? $data['dateTo'] ?? null,
            status: isset($data['status']) ? CommissionStatus::from($data['status']) : null,
            method: $method,
            type: isset($data['type']) ? CommissionType::from($data['type']) : null,
            total: isset($data['total']) ? (float) $data['total'] : null,
            page: $data['page'] ?? 1,
            perPage: $data['per_page'] ?? $data['perPage'] ?? 15,
            sort: $data['sort_by'] ?? 'pickup_first',
            sortDirection: $data['sort_direction'] ?? 'asc'
        );
    }
}
