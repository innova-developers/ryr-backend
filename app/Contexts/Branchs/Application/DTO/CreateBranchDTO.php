<?php

namespace App\Contexts\Branchs\Application\DTO;

class CreateBranchDTO
{
    public string $name;
    public string $address;
    public string $phone;
    public string $email;

    public function __construct(string $name, string $address, string $phone, string $schedule)
    {
        $this->name = $name;
        $this->address = $address;
        $this->phone = $phone;
        $this->schedule = $schedule;
    }

}
