<?php

namespace App\Contexts\Customers\Infrastructure\Http\Requests;

use App\Shared\Enums\CustomerType;
use App\Shared\Enums\IvaStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateCustomerRequest extends FormRequest
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
        // El estado de IVA es opcional en el alta; si no viene, el DTO cae en 'auto'.
        $ivaStatus = ['iva_status' => ['nullable', Rule::in(IvaStatus::values())]];

        // Empresa se identifica por CUIT + razón social; cliente común por DNI + nombre.
        if ($this->input('type') === CustomerType::COMPANY->value) {
            return $ivaStatus + [
                'type' => ['required', Rule::in(CustomerType::values())],
                'razon_social' => ['required', 'string', 'max:255'],
                'cuit' => ['required', 'string', 'max:13'],
                'dni' => ['nullable'],
                'email' => ['nullable', 'email', 'unique:customers,email'],
            ];
        }

        return $ivaStatus + [
            'type' => ['nullable', Rule::in(CustomerType::values())],
            'dni' => ['required'],
            'name' => ['required', 'string'],
            'email' => ['nullable', 'email', 'unique:customers,email'],
        ];
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
