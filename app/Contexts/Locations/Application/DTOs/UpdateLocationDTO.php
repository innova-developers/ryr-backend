<?php

namespace App\Contexts\Locations\Application\DTOs;

class UpdateLocationDTO
{
    public int $id;
    public string $name;
    public string $address;
    public string $origin;
    public string $phone;
    public ?string $map;
    public string $schedule;
    public ?string $observation;
    public function __construct(
        int $id,
        string $name,
        string $address,
        string $origin,
        string $phone,
        ?string $map,
        string $schedule,
        ?string $observation
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->address = $address;
        $this->origin = $origin;
        $this->phone = $phone;
        $this->map = $map;
        $this->schedule = $schedule;
        $this->observation = $observation;
    }
}
