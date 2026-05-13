<?php

namespace App\Contexts\Customers\Application\DTO;

class UpdateCustomerDTO
{
    public int $id;
    public int $dni;
    public ?string $cuit;
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
    public bool $autoCalculateIva;
    public ?int $userId;
    public int $branchId;
    public ?int $internalUserId;
    public function __construct(
        int $id,
        int $dni,
        ?string $cuit,
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
        bool $autoCalculateIva = true,
        ?int $userId = null,
        int $branchId,
        ?int $internalUserId = null
    ) {
        $this->id = $id;
        $this->dni = $dni;
        $this->cuit = $cuit;
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
        $this->autoCalculateIva = $autoCalculateIva;
        $this->userId = $userId;
        $this->branchId = $branchId;
        $this->internalUserId = $internalUserId;
    }

}
