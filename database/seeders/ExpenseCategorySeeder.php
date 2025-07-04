<?php

namespace Database\Seeders;

use App\Shared\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Transportes',
                'description' => 'Gastos relacionados con transportes y logística',
                'is_active' => true,
            ],
            [
                'name' => 'Combustible',
                'description' => 'Gastos de combustible y lubricantes',
                'is_active' => true,
            ],
            [
                'name' => 'Mantenimiento',
                'description' => 'Gastos de mantenimiento de vehículos y equipos',
                'is_active' => true,
            ],
            [
                'name' => 'Peajes',
                'description' => 'Gastos de peajes y viáticos',
                'is_active' => true,
            ],
            [
                'name' => 'Seguros',
                'description' => 'Gastos de seguros y coberturas',
                'is_active' => true,
            ],
            [
                'name' => 'Oficina',
                'description' => 'Gastos de oficina y administración',
                'is_active' => true,
            ],
            [
                'name' => 'Marketing',
                'description' => 'Gastos de marketing y publicidad',
                'is_active' => true,
            ],
            [
                'name' => 'Otros',
                'description' => 'Otros gastos varios',
                'is_active' => true,
            ],
            [
                'name' => 'Adelantos',
                'description' => 'Adelantos de Sueldos',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ExpenseCategory::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
