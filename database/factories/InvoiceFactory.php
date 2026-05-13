<?php

namespace Database\Factories;

use App\Shared\Enums\InvoiceType;
use App\Shared\Models\Branch;
use App\Shared\Models\Customer;
use App\Shared\Models\Invoice;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create();

        return [
            'customer_id' => $customer->id,
            'commission_id' => null,
            'current_account_id' => null,
            'franchise_id' => null,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'tipo_comprobante' => InvoiceType::FACTURA_B->value,
            'punto_venta' => 5,
            'numero_comprobante' => $this->faker->numberBetween(1, 99999),
            'fecha_emision' => $this->faker->date(),
            'cae' => str_pad((string) $this->faker->numberBetween(10000000000000, 99999999999999), 14, '0', STR_PAD_LEFT),
            'cae_vencimiento' => date('Ymd', strtotime('+10 days')),
            'importe_total' => $this->faker->randomFloat(2, 100, 50000),
            'importe_neto' => $this->faker->randomFloat(2, 80, 40000),
            'importe_iva' => $this->faker->randomFloat(2, 20, 10000),
            'iva_rate' => 21,
            'doc_tipo' => 99,
            'doc_numero' => null,
            'razon_social' => $this->faker->company(),
            'domicilio_cliente' => $this->faker->address(),
            'condicion_iva' => 'Consumidor Final',
            'concepto' => 2,
            'status' => 'emitida',
            'observaciones' => null,
        ];
    }

    public function facturaA(): static
    {
        return $this->state(fn () => [
            'tipo_comprobante' => InvoiceType::FACTURA_A->value,
            'doc_tipo' => 80,
            'doc_numero' => (string) $this->faker->numberBetween(20000000001, 33999999999),
            'condicion_iva' => 'IVA Responsable Inscripto',
        ]);
    }

    public function anulada(): static
    {
        return $this->state(fn () => ['status' => 'anulada']);
    }
}
