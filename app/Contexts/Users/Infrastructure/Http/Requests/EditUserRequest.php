<?php

namespace App\Contexts\Users\Infrastructure\Http\Requests;

use App\Shared\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class EditUserRequest extends FormRequest
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
        $userId = $this->route('id') ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $userId,
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', new Enum(UserRole::class)],
            'branch_id' => ['required', 'exists:branches,id'],
            'base_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'income_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contract_type' => ['required', 'in:fixed_salary,commission_based'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
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
