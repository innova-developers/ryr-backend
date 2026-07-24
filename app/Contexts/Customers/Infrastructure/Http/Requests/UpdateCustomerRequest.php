<?php

namespace App\Contexts\Customers\Infrastructure\Http\Requests;

use App\Shared\Enums\CustomerType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Email único ignorando al propio cliente que se actualiza.
        $rules = [
            'type' => ['nullable', Rule::in(CustomerType::values())],
            'email' => ['nullable', 'email', Rule::unique('customers', 'email')->ignore($this->route('id'))],
        ];

        // Empresa requiere razón social + CUIT.
        if ($this->input('type') === CustomerType::COMPANY->value) {
            $rules['razon_social'] = ['required', 'string', 'max:255'];
            $rules['cuit'] = ['required', 'string', 'max:13'];
        }

        return $rules;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Datos inválidos: ' . implode(', ', $validator->errors()->all()),
            ], 422)
        );
    }
}
