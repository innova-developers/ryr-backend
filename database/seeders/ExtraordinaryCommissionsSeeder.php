<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Shared\Models\ExtraordinaryCommission;

class ExtraordinaryCommissionsSeeder extends Seeder
{
    public function run(): void
    {
        $extraordinary_commissions = [
            [
                'origin' => 'Buenos Aires',
                'destination' => 'Córdoba',
                'detail' => "Cama de 2 Plazas",
                'price' => 1200.00,
                'observations' => "Hasta 1.90 x 1.40"
            ],
            [
                'origin' => 'Buenos Aires',
                'destination' => 'Rosario',
                'detail' => "Cocina",
                'price' => 12000.00,
                'observations' => "Cocina tipo de 4 hornallas"
            ],
        ];

        foreach ($extraordinary_commissions as $extraordinary_commission) {
            ExtraordinaryCommission::create($extraordinary_commission);
        }
    }
}
