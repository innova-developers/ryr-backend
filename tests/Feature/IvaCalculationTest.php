<?php

namespace Tests\Feature;

use App\Services\IvaCalculationService;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Customer;
use App\Shared\Models\Commission;
use App\Shared\Models\Branch;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IvaCalculationTest extends TestCase
{
    use RefreshDatabase;

    private IvaCalculationService $ivaCalculationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ivaCalculationService = new IvaCalculationService();
    }

    /** @test */
    public function it_calculates_iva_for_transfer_payment_method_when_customer_has_auto_calculate_iva_enabled()
    {
        // Crear cliente con auto_calculate_iva habilitado
        $customer = Customer::factory()->create([
            'auto_calculate_iva' => true
        ]);

        $total = 1000.00;
        $paymentMethod = PaymentMethod::TRANSFERENCIA;

        $result = $this->ivaCalculationService->calculateIva($customer, $paymentMethod, $total);

        $this->assertTrue($result['iva_applied']);
        $this->assertEquals(210.00, $result['iva_amount']); // 21% de 1000
        $this->assertEquals(1210.00, $result['total_with_iva']);
        $this->assertEquals(1000.00, $result['total_without_iva']);
    }

    /** @test */
    public function it_does_not_calculate_iva_for_transfer_payment_method_when_customer_has_auto_calculate_iva_disabled()
    {
        // Crear cliente con auto_calculate_iva deshabilitado
        $customer = Customer::factory()->create([
            'auto_calculate_iva' => false
        ]);

        $total = 1000.00;
        $paymentMethod = PaymentMethod::TRANSFERENCIA;

        $result = $this->ivaCalculationService->calculateIva($customer, $paymentMethod, $total);

        $this->assertFalse($result['iva_applied']);
        $this->assertEquals(0.00, $result['iva_amount']);
        $this->assertEquals(1000.00, $result['total_with_iva']);
        $this->assertEquals(1000.00, $result['total_without_iva']);
    }

    /** @test */
    public function it_does_not_calculate_iva_for_non_transfer_payment_methods()
    {
        $customer = Customer::factory()->create([
            'auto_calculate_iva' => true
        ]);

        $total = 1000.00;
        $paymentMethods = [
            PaymentMethod::EFECTIVO,
            PaymentMethod::CHEQUE,
            PaymentMethod::CUENTA_CORRIENTE
        ];

        foreach ($paymentMethods as $paymentMethod) {
            $result = $this->ivaCalculationService->calculateIva($customer, $paymentMethod, $total);

            $this->assertFalse($result['iva_applied'], "IVA should not be applied for payment method: {$paymentMethod->value}");
            $this->assertEquals(0.00, $result['iva_amount']);
            $this->assertEquals(1000.00, $result['total_with_iva']);
        }
    }

    /** @test */
    public function it_applies_iva_to_commission_when_conditions_are_met()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create([
            'auto_calculate_iva' => true,
            'branch_id' => $branch->id
        ]);

        // Crear destino necesario
        $destination = \App\Shared\Models\Destination::factory()->create();
        
        // Crear ubicaciones necesarias
        $originLocation = \App\Shared\Models\Location::factory()->create();
        $destinationLocation = \App\Shared\Models\Location::factory()->create();

        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'payment_method' => PaymentMethod::TRANSFERENCIA,
            'total' => 1000.00,
            'iva_amount' => 0.00,
            'iva_applied' => false
        ]);

        $updatedCommission = $this->ivaCalculationService->applyIvaToCommission($commission);

        $this->assertTrue($updatedCommission->iva_applied);
        $this->assertEquals(210.00, $updatedCommission->iva_amount);
        $this->assertEquals(1210.00, $updatedCommission->total);
    }

    /** @test */
    public function it_removes_iva_from_commission()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id
        ]);

        // Crear destino necesario
        $destination = \App\Shared\Models\Destination::factory()->create();
        
        // Crear ubicaciones necesarias
        $originLocation = \App\Shared\Models\Location::factory()->create();
        $destinationLocation = \App\Shared\Models\Location::factory()->create();

        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'total' => 1210.00,
            'iva_amount' => 210.00,
            'iva_applied' => true
        ]);

        $updatedCommission = $this->ivaCalculationService->removeIvaFromCommission($commission);

        $this->assertFalse($updatedCommission->iva_applied);
        $this->assertEquals(0.00, $updatedCommission->iva_amount);
        $this->assertEquals(1000.00, $updatedCommission->total);
    }

    /** @test */
    public function it_updates_iva_when_payment_method_changes()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create([
            'auto_calculate_iva' => true,
            'branch_id' => $branch->id
        ]);

        // Crear destino necesario
        $destination = \App\Shared\Models\Destination::factory()->create();
        
        // Crear ubicaciones necesarias
        $originLocation = \App\Shared\Models\Location::factory()->create();
        $destinationLocation = \App\Shared\Models\Location::factory()->create();

        // Comisión inicial con EFECTIVO (sin IVA)
        $commission = Commission::factory()->create([
            'client_id' => $customer->id,
            'destination_id' => $destination->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'origin_location_id' => $originLocation->id,
            'destination_location_id' => $destinationLocation->id,
            'payment_method' => PaymentMethod::EFECTIVO,
            'total' => 1000.00,
            'iva_amount' => 0.00,
            'iva_applied' => false
        ]);

        // Cambiar a TRANSFERENCIA (debe aplicar IVA)
        $updatedCommission = $this->ivaCalculationService->updateIvaForPaymentMethodChange(
            $commission, 
            PaymentMethod::TRANSFERENCIA
        );

        $this->assertTrue($updatedCommission->iva_applied);
        $this->assertEquals(210.00, $updatedCommission->iva_amount);
        $this->assertEquals(1210.00, $updatedCommission->total);
    }

    /** @test */
    public function it_generates_correct_iva_note()
    {
        $note = $this->ivaCalculationService->generateIvaNote(210.00, true);
        
        $this->assertEquals('IVA (21%) aplicado automáticamente: $210.00', $note);
    }

    /** @test */
    public function it_returns_empty_note_when_iva_not_applied()
    {
        $note = $this->ivaCalculationService->generateIvaNote(0.00, false);
        
        $this->assertEquals('', $note);
    }

    /** @test */
    public function it_returns_correct_iva_percentage()
    {
        $percentage = $this->ivaCalculationService->getIvaPercentage();
        
        $this->assertEquals(21.0, $percentage);
    }
}
