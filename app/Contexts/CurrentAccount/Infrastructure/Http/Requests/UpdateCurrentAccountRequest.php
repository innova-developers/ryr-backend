<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCurrentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'sometimes|in:credit,debit',
            'amount' => 'sometimes|numeric|min:0.01',
            'description' => 'sometimes|string|max:255',
            'reference' => 'nullable|string|max:255',
            'transaction_date' => 'sometimes|date',
            'payment_method' => 'nullable|in:cash,transfer,check,card,other',
            'observations' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'El tipo debe ser credit o debit',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
            'transaction_date.date' => 'La fecha debe ser válida',
            'payment_method.in' => 'El método de pago no es válido',
        ];
    }
}
