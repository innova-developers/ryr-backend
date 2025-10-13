<?php

namespace App\Contexts\Transports\Application\DTOs;

class CreateTransportDTO
{
    public string $plate;
    public string $description;
    public string $phone;
    public string $insurance;
    public string $usage;
    public ?string $observation;
    public function __construct(
        string $plate,
        string $description,
        string $phone,
        string $insurance,
        string $usage,
        ?string $observation = null,
    ) {
        $this->plate = $plate;
        $this->description = $description;
        $this->phone = $phone;
        $this->insurance = $insurance;
        $this->usage = $usage;
        $this->observation = $observation;
    }


}
