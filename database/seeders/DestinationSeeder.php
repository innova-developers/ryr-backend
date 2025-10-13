<?php

namespace Database\Seeders;

use App\Shared\Models\Destination;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            [
                'origin' => 'Buenos Aires',
                'destination' => 'Córdoba',
                'fixed_price' => 1500.00,
                'small_bulk_price' => 1200.00,
                'large_bulk_price' => 900.00,
            ],
            [
                'origin' => 'Buenos Aires',
                'destination' => 'Rosario',
                'fixed_price' => 800.00,
                'small_bulk_price' => 600.00,
                'large_bulk_price' => 450.00,
            ],
            [
                'origin' => 'Buenos Aires',
                'destination' => 'Mendoza',
                'fixed_price' => 2000.00,
                'small_bulk_price' => 1600.00,
                'large_bulk_price' => 1200.00,
            ],
            [
                'origin' => 'Córdoba',
                'destination' => 'Rosario',
                'fixed_price' => 900.00,
                'small_bulk_price' => 700.00,
                'large_bulk_price' => 500.00,
            ],
            [
                'origin' => 'Córdoba',
                'destination' => 'Mendoza',
                'fixed_price' => 1200.00,
                'small_bulk_price' => 900.00,
                'large_bulk_price' => 700.00,
            ],
            [
                'origin' => 'Rosario',
                'destination' => 'Mendoza',
                'fixed_price' => 1500.00,
                'small_bulk_price' => 1200.00,
                'large_bulk_price' => 900.00,
            ],
            [
                'origin' => 'Buenos Aires',
                'destination' => 'Mar del Plata',
                'fixed_price' => 1000.00,
                'small_bulk_price' => 800.00,
                'large_bulk_price' => 600.00,
            ],
            [
                'origin' => 'Buenos Aires',
                'destination' => 'La Plata',
                'fixed_price' => 500.00,
                'small_bulk_price' => 400.00,
                'large_bulk_price' => 300.00,
            ],
            [
                'origin' => 'Córdoba',
                'destination' => 'Tucumán',
                'fixed_price' => 1800.00,
                'small_bulk_price' => 1400.00,
                'large_bulk_price' => 1000.00,
            ],
            [
                'origin' => 'Mendoza',
                'destination' => 'San Juan',
                'fixed_price' => 600.00,
                'small_bulk_price' => 450.00,
                'large_bulk_price' => 350.00,
            ],
        ];

        foreach ($destinations as $destination) {
            Destination::create($destination);
        }
    }
}
