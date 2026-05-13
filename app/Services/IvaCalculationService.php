<?php

namespace App\Services;

use App\Shared\Enums\IvaStatus;
use App\Shared\Enums\PaymentMethod;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Shared\Models\SystemSetting;

class IvaCalculationService
{
    /**
     * Porcentaje de IVA aplicado (21%)
     */
    public const IVA_PERCENTAGE = 21.0;

    /**
     * Calcula el IVA para una comisión basado en el cliente y método de pago
     *
     * @param Customer $customer
     * @param PaymentMethod $paymentMethod
     * @param float $total
     * @return array
     */
    public function calculateIva(Customer $customer, PaymentMethod $paymentMethod, float $total): array
    {
        $shouldApplyIva = $this->shouldApplyIva($customer, $paymentMethod);
        
        if (!$shouldApplyIva) {
            return [
                'iva_amount' => 0.0,
                'iva_applied' => false,
                'total_with_iva' => $total,
                'total_without_iva' => $total
            ];
        }

        $ivaAmount = $this->calculateIvaAmount($total);
        $totalWithIva = $total + $ivaAmount;

        return [
            'iva_amount' => $ivaAmount,
            'iva_applied' => true,
            'total_with_iva' => $totalWithIva,
            'total_without_iva' => $total
        ];
    }

    /**
     * Determina si se debe aplicar IVA basado en el cliente y método de pago
     *
     * @param Customer $customer
     * @param PaymentMethod $paymentMethod
     * @return bool
     */
    public function shouldApplyIva(Customer $customer, PaymentMethod $paymentMethod): bool
    {
        $ivaStatus = $customer->iva_status ?? IvaStatus::AUTO;

        if ($ivaStatus === IvaStatus::ALWAYS) {
            return true;
        }

        if ($ivaStatus === IvaStatus::EXEMPT) {
            return false;
        }

        $enabledMethods = SystemSetting::get('iva_payment_methods', ['TRANSFERENCIA']);
        return $customer->auto_calculate_iva && in_array($paymentMethod->value, $enabledMethods);
    }

    /**
     * Calcula el monto del IVA (21% del total)
     *
     * @param float $total
     * @return float
     */
    public function calculateIvaAmount(float $total): float
    {
        return round($total * (self::IVA_PERCENTAGE / 100), 2);
    }

    /**
     * Aplica el IVA a una comisión existente
     *
     * @param Commission $commission
     * @return Commission
     */
    public function applyIvaToCommission(Commission $commission): Commission
    {
        $customer = $commission->client;
        $paymentMethod = $commission->payment_method;
        $currentTotal = $commission->total;

        $ivaCalculation = $this->calculateIva($customer, $paymentMethod, $currentTotal);

        $commission->update([
            'iva_amount' => $ivaCalculation['iva_amount'],
            'iva_applied' => $ivaCalculation['iva_applied'],
            'total' => $ivaCalculation['total_with_iva']
        ]);

        return $commission;
    }

    /**
     * Remueve el IVA de una comisión
     *
     * @param Commission $commission
     * @return Commission
     */
    public function removeIvaFromCommission(Commission $commission): Commission
    {
        $totalWithoutIva = $commission->total - $commission->iva_amount;

        $commission->update([
            'iva_amount' => 0.0,
            'iva_applied' => false,
            'total' => $totalWithoutIva
        ]);

        return $commission;
    }

    /**
     * Actualiza el IVA de una comisión cuando cambia el método de pago
     *
     * @param Commission $commission
     * @param PaymentMethod $newPaymentMethod
     * @return Commission
     */
    public function updateIvaForPaymentMethodChange(Commission $commission, PaymentMethod $newPaymentMethod): Commission
    {
        $customer = $commission->client;
        $currentTotal = $commission->total - $commission->iva_amount; // Total sin IVA

        $ivaCalculation = $this->calculateIva($customer, $newPaymentMethod, $currentTotal);

        $commission->update([
            'payment_method' => $newPaymentMethod,
            'iva_amount' => $ivaCalculation['iva_amount'],
            'iva_applied' => $ivaCalculation['iva_applied'],
            'total' => $ivaCalculation['total_with_iva']
        ]);

        return $commission;
    }

    /**
     * Genera una nota descriptiva del IVA aplicado
     *
     * @param float $ivaAmount
     * @param bool $applied
     * @return string
     */
    public function generateIvaNote(float $ivaAmount, bool $applied): string
    {
        if (!$applied || $ivaAmount == 0) {
            return '';
        }

        return sprintf(
            'IVA (%s%%) aplicado automáticamente: $%.2f',
            self::IVA_PERCENTAGE,
            $ivaAmount
        );
    }

    /**
     * Obtiene el porcentaje de IVA configurado
     *
     * @return float
     */
    public function getIvaPercentage(): float
    {
        return self::IVA_PERCENTAGE;
    }
}
