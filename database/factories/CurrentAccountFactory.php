<?php

namespace Database\Factories;

use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Shared\Models\CurrentAccount>
 */
class CurrentAccountFactory extends Factory
{
    protected $model = CurrentAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['credit', 'debit']);
        $amount = $this->faker->randomFloat(2, 100, 10000);
        $transactionDate = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'customer_id' => Customer::factory(),
            'type' => $type,
            'amount' => $amount,
            // Por defecto las transacciones sembradas están confirmadas (OK), así
            // cuentan para el cálculo de saldo (getCustomerBalance solo suma OK).
            'status' => \App\Shared\Enums\CurrentAccountStatus::OK->value,
            'description' => $this->faker->sentence(3),
            'reference' => $this->faker->optional()->bothify('REF-####-????'),
            'transaction_date' => $transactionDate,
            'balance' => 0, // Se calculará en el repositorio
            'payment_method' => $this->faker->randomElement(['cash', 'transfer', 'check', 'card', 'other']),
            'observations' => $this->faker->optional()->paragraph(),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the transaction is a credit (ingreso).
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'credit',
        ]);
    }

    /**
     * Indicate that the transaction is a debit (egreso).
     */
    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'debit',
        ]);
    }

    /**
     * Indicate that the payment method is cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Indicate that the payment method is transfer.
     */
    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'transfer',
        ]);
    }
} 