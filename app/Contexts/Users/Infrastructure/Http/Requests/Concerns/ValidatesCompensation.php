<?php

namespace App\Contexts\Users\Infrastructure\Http\Requests\Concerns;

use App\Shared\Enums\ContractType;
use Illuminate\Validation\Rule;

/**
 * Reglas de la remuneración del empleado: tipo de contratación y los montos que
 * exige cada uno.
 *
 * Las comparten el alta (CreateUserRequest) y la edición (EditUserRequest):
 * cuando cada request tenía su propia lista, las dos quedaron clavadas en
 * fixed_salary/commission_based después de que la columna pasara a los tres
 * tipos nuevos, y el modal de Empleados no podía guardar "Fijo + Comisiones"
 * ni "Por Retiro" (RC-532).
 *
 * "Sueldo Fijo" sigue sin exigir sueldo base del lado del backend, como hasta
 * ahora; el formulario lo pide igual.
 */
trait ValidatesCompensation
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function compensationRules(): array
    {
        $fixedPlusCommission = ContractType::FIXED_PLUS_COMMISSION->value;

        return [
            'base_salary' => ['nullable', "required_if:contract_type,{$fixedPlusCommission}", 'numeric', 'min:0', 'max:9999999.99'],
            'income_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_percentage' => ['nullable', "required_if:contract_type,{$fixedPlusCommission}", 'numeric', 'min:0', 'max:100'],
            'contract_type' => ['required', Rule::in(ContractType::values())],
            'payment_per_pickup' => ['nullable', 'required_if:contract_type,' . ContractType::PER_PICKUP->value, 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function compensationMessages(): array
    {
        return [
            'base_salary.required_if' => 'El sueldo base es obligatorio para la contratación Fijo + Comisiones.',
            'commission_percentage.required_if' => 'El porcentaje de comisiones es obligatorio para la contratación Fijo + Comisiones.',
            'payment_per_pickup.required_if' => 'El monto por retiro es obligatorio para la contratación Por Retiro.',
        ];
    }
}
