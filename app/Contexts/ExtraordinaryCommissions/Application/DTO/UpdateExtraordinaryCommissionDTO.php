<?php

namespace App\Contexts\ExtraordinaryCommissions\Application\DTO;

class UpdateExtraordinaryCommissionDTO
{
    public int $id;
    public string $origin;
    public string $destination;
    public string $detail;
    public float $price;
    public ?string $observations;
    public function __construct(
        int $id,
        string $origin,
        string $destination,
        string $detail,
        float $price,
        ?string $observations
    ) {
        $this->id = $id;
        $this -> origin = $origin;
        $this -> destination = $destination;
        $this -> detail = $detail;
        $this -> price = $price;
        $this -> observations = $observations ?? null;
    }

}
