<?php

namespace App\Contexts\Incomes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'income_category_id' => 'required|integer|exists:income_categories,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'date' => 'required|date',
            'detail' => 'required|string|max:1000',
            'amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'income_category_id.required' => 'La categoría es obligatoria',
            'income_category_id.exists' => 'La categoría seleccionada no existe',
            'user_id.exists' => 'El usuario seleccionado no existe',
            'date.required' => 'La fecha es obligatoria',
            'date.date' => 'La fecha debe tener un formato válido',
            'detail.required' => 'El detalle es obligatorio',
            'detail.max' => 'El detalle no puede tener más de 1000 caracteres',
            'amount.required' => 'El monto es obligatorio',
            'amount.numeric' => 'El monto debe ser un número',
            'amount.min' => 'El monto debe ser mayor a 0',
        ];
    }
}
