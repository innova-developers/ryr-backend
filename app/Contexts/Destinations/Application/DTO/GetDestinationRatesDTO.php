<?php

namespace App\Contexts\Destinations\Application\DTO;

class GetDestinationRatesDTO
{
    public string $origin;
    public string $destination;
    public function __construct(
         string $origin,
         string $destination
    ) {
        $this->origin = $origin;
        $this->destination = $destination;
    }

}
