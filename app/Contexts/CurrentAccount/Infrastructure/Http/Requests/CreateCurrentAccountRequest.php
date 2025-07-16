<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCurrentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|integer|exists:customers,id',
            'type' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|in:cash,transfer,check,card,other',
            'observations' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'El cliente es requerido',
            'customer_id.exists' => 'El cliente seleccionado no existe',
            'type.required' => 'El tipo de transacción es requerido',
            'type.in' => 'El tipo debe ser credit o debit',
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
            'description.required' => 'La descripción es requerida',
            'transaction_date.required' => 'La fecha de transacción es requerida',
            'transaction_date.date' => 'La fecha debe ser válida',
            'payment_method.in' => 'El método de pago no es válido',
        ];
    }
}
