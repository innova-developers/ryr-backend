<?php

namespace App\Contexts\Franchises\Application\DTO;

class UpdateFranchiseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $slug = null,
        public readonly ?string $address = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly float $commission_percentage_to_matrix = 0,
        public readonly ?int $owner_user_id = null,
        public readonly string $status = 'active',
        public readonly ?string $contract_start_date = null,
        public readonly ?string $contract_end_date = null,
        public readonly ?array $settings = null,
    ) {
    }

    public static function fromArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            name: $data['name'],
            slug: $data['slug'] ?? null,
            address: $data['address'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            commission_percentage_to_matrix: (float) ($data['commission_percentage_to_matrix'] ?? 0),
            owner_user_id: isset($data['owner_user_id']) ? (int) $data['owner_user_id'] : null,
            status: $data['status'] ?? 'active',
            contract_start_date: $data['contract_start_date'] ?? null,
            contract_end_date: $data['contract_end_date'] ?? null,
            settings: $data['settings'] ?? null,
        );
    }
}
