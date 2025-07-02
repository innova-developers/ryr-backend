<?php

namespace Database\Seeders;

use App\Shared\Models\IncomeCategory;
use Illuminate\Database\Seeder;

class IncomeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Ventas',
                'description' => 'Ingresos por ventas de productos o servicios',
            ],
            [
                'name' => 'Comisiones',
                'description' => 'Ingresos por comisiones de servicios',
            ],
            [
                'name' => 'Inversiones',
                'description' => 'Ingresos por inversiones financieras',
            ],
            [
                'name' => 'Otros',
                'description' => 'Otros tipos de ingresos',
            ],
        ];

        foreach ($categories as $category) {
            IncomeCategory::create($category);
        }
    }
} 