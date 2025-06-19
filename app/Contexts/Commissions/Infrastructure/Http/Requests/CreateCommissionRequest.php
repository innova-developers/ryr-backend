<?php

namespace App\Contexts\Commissions\Infrastructure\Http\Requests;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use App\Shared\Enums\CommissionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:customers,id'],
            'date' => ['required', 'date'],
            'origin' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(CommissionStatus::class)],
            'origin_location_id' => ['required', 'integer', 'exists:locations,id'],
            'destination_location_id' => ['required', 'integer', 'exists:locations,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', new Enum(CommissionItemType::class)],
            'items.*.size' => ['required_if:items.*.type,ordinaria', new Enum(CommissionItemSize::class)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric', 'min:0'],
            'items.*.detail' => ['required_if:items.*.type,extraordinaria', 'string', 'max:255'],
            'total' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.required' => 'El ID del cliente es requerido',
            'client_id.exists' => 'El cliente no existe',
            'date.required' => 'La fecha es requerida',
            'date.date' => 'La fecha debe ser una fecha válida',
            'origin.required' => 'El origen es requerido',
            'destination.required' => 'El destino es requerido',
            'status.required' => 'El estado es requerido',
            'origin_location_id.required' => 'La ubicación de origen es requerida',
            'origin_location_id.exists' => 'La ubicación de origen no existe',
            'destination_location_id.required' => 'La ubicación de destino es requerida',
            'destination_location_id.exists' => 'La ubicación de destino no existe',
            'items.required' => 'La comisión debe contener al menos un item',
            'items.min' => 'La comisión debe contener al menos un item',
            'items.*.type.required' => 'El tipo de item es requerido',
            'items.*.size.required_if' => 'El tamaño es requerido para items ordinarios',
            'items.*.quantity.required' => 'La cantidad es requerida',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a 0',
            'items.*.unit_price.required' => 'El precio unitario es requerido',
            'items.*.unit_price.min' => 'El precio unitario debe ser mayor a 0',
            'items.*.subtotal.required' => 'El subtotal es requerido',
            'items.*.subtotal.min' => 'El subtotal debe ser mayor a 0',
            'items.*.detail.required_if' => 'El detalle es requerido para items extraordinarios',
            'total.required' => 'El total es requerido',
            'total.min' => 'El total debe ser mayor a 0',
        ];
    }
}
