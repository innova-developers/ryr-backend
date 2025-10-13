<?php

namespace App\Contexts\Expenses\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transport_id' => 'nullable|integer|exists:transports,id',
            'expense_category_id' => 'nullable|integer|exists:expense_categories,id',
            'date' => ['required', 'date'],
            'detail' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'transport_id.integer' => 'El ID del transporte debe ser un número entero',
            'transport_id.exists' => 'El transporte especificado no existe',
            'expense_category_id.integer' => 'El ID de la categoría debe ser un número entero',
            'expense_category_id.exists' => 'La categoría especificada no existe',
            'date.required' => 'La fecha es requerida',
            'date.date' => 'La fecha debe ser una fecha válida',
            'detail.required' => 'El detalle es requerido',
            'detail.max' => 'El detalle no puede tener más de 255 caracteres',
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor o igual a 0',
        ];
    }
}
