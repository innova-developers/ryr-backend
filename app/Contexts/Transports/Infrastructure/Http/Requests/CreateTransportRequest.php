<?php

namespace App\Contexts\Transports\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTransportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plate' => 'required|string|max:10',
            'description' => 'required|string|max:255',
            'phone' => 'nullable|string|max:15',
            'insurance' => 'nullable|string|max:255',
            'usage' => 'nullable|string|max:255',
            'observation' => 'nullable|string|max:255',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'plate.required' => 'La placa es obligatoria.',
            'plate.string' => 'La placa debe ser una cadena de texto.',
            'plate.max' => 'La placa no puede exceder los 10 caracteres.',
            'description.required' => 'La descripción es obligatoria.',
            'description.string' => 'La descripción debe ser una cadena de texto.',
            'description.max' => 'La descripción no puede exceder los 255 caracteres.',
            'phone.string' => 'El teléfono debe ser una cadena de texto.',
            'phone.max' => 'El teléfono no puede exceder los 15 caracteres.',
            'insurance.string' => 'El seguro debe ser una cadena de texto.',
            'insurance.max' => 'El seguro no puede exceder los 255 caracteres.',
            'usage.string' => 'El uso debe ser una cadena de texto.',
            'usage.max' => 'El uso no puede exceder los 255 caracteres.',
            'observation.string' => 'La observación debe ser una cadena de texto.',
            'observation.max' => 'La observación no puede exceder los 255 caracteres.',
        ];
    }

}
