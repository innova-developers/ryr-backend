<?php

namespace Database\Factories;

use App\Shared\Models\CommissionItem;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionItemSize;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionItemFactory extends Factory
{
    protected $model = CommissionItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 100, 1000);
        $quantity = $this->faker->numberBetween(1, 5);
        $type = $this->faker->randomElement([CommissionItemType::ORDINARIA->value, CommissionItemType::EXTRAORDINARIA->value]);

        return [
            'commission_id' => 1,
            'type' => $type,
            'size' => $type === CommissionItemType::ORDINARIA->value ? 
                $this->faker->randomElement([CommissionItemSize::SMALL->value, CommissionItemSize::LARGE->value]) : 
                null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $quantity,
            'detail' => $type === CommissionItemType::EXTRAORDINARIA->value ? 
                $this->faker->sentence() : 
                null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
} 