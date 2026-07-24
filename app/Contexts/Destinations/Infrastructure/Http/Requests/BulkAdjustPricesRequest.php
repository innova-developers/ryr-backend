<?php

namespace App\Contexts\Destinations\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class BulkAdjustPricesRequest extends FormRequest
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
        return [
            'percentage' => 'required|numeric|min:-100|max:1000',
            'fixed_price' => 'required|boolean',
            'small_bulk_price' => 'required|boolean',
            'large_bulk_price' => 'required|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('fixed_price')
                && ! $this->boolean('small_bulk_price')
                && ! $this->boolean('large_bulk_price')) {
                $validator->errors()->add('fixed_price', 'Debe seleccionar al menos un precio a actualizar');
            }
        });
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
