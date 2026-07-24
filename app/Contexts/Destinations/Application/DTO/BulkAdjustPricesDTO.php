<?php

namespace App\Contexts\Destinations\Application\DTO;

class BulkAdjustPricesDTO
{
    public float $percentage;
    public bool $fixed_price;
    public bool $small_bulk_price;
    public bool $large_bulk_price;

    public function __construct(
        float $percentage,
        bool $fixed_price,
        bool $small_bulk_price,
        bool $large_bulk_price
    ) {
        $this->percentage = $percentage;
        $this->fixed_price = $fixed_price;
        $this->small_bulk_price = $small_bulk_price;
        $this->large_bulk_price = $large_bulk_price;
    }
}
