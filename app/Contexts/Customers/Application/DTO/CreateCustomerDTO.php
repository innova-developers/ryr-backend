<?php

namespace App\Contexts\Customers\Application\DTO;

class CreateCustomerDTO
{
    public int $dni;
    public string $name;
    public string $lastName;
    public ?string $mobile;
    public string $email;
    public ?string $address;
    public ?string $city;
    public ?string $phone;
    public ?string $mapsUrl;
    public ?string $businessHours;
    public ?string $observations;
    public bool $isPremium;
    public ?int $userId;
    public int $branchId;
    public function __construct(
        int $dni,
        string $name,
        string $lastName,
        ?string $mobile,
        string $email,
        ?string $address,
        ?string $city,
        ?string $phone,
        ?string $mapsUrl = null,
        ?string $businessHours = null,
        ?string $observations = null,
        bool $isPremium = false,
        ?int $userId = null,
        int $branchId
    ) {
        $this->dni = $dni;
        $this->name = $name;
        $this->lastName = $lastName;
        $this->mobile = $mobile;
        $this->email = $email;
        $this->address = $address;
        $this->city = $city;
        $this->phone = $phone;
        $this->mapsUrl = $mapsUrl;
        $this->businessHours = $businessHours;
        $this->observations = $observations;
        $this->isPremium = $isPremium;
        $this->userId = $userId;
        $this->branchId = $branchId;
    }

}
