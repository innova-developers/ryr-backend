<?php

namespace App\Contexts\Destinations\Application\DTO;

class CreateDestinationDTO
{
    public string $origin;
    public string $destination;

    public float $fixed_price;
    public float $small_bulk_price;
    public float $large_bulk_price;
    public function __construct(
        string $origin,
        string $destination,
        float $fixed_price,
        float $small_bulk_price,
        float $large_bulk_price
    ) {
        $this -> origin = $origin;
        $this -> destination = $destination;
        $this -> fixed_price = $fixed_price;
        $this -> small_bulk_price = $small_bulk_price;
        $this -> large_bulk_price = $large_bulk_price;
    }

}
