<?php

namespace App\Contexts\ExtraordinaryCommissions\Application\DTO;

class GetExtraordinaryCommissionByOriginAndDestinationDTO
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
