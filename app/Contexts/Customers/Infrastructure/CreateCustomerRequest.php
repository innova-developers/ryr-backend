<?php

namespace App\Contexts\Customers\Application\UseCases\CreateCustomer;

class CreateCustomerRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $lastName,
        public readonly ?string $mobile,
        public readonly string $email,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $phone,
        public readonly ?string $mapsUrl,
        public readonly ?string $businessHours,
        public readonly ?string $observations,
        public readonly bool $isPremium,
        public readonly ?int $userId
    ) {
    }
}
