<?php

namespace App\Contexts\Branchs\Application\DTO;

class UpdateBranchDTO
{
    public int $id;
    public string $name;
    public string $address;
    public string $phone;
    public string $schedule;
    public ?string $secondary_phone;

    public function __construct(int $id, string $name, string $address, string $phone, string $schedule, ?string $secondary_phone = null)
    {
        $this->name = $name;
        $this->address = $address;
        $this->phone = $phone;
        $this->schedule = $schedule;
        $this->secondary_phone = $secondary_phone;
        $this->id = $id;
    }

}
