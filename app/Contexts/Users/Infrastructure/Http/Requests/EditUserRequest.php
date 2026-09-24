<?php

namespace App\Contexts\Users\Infrastructure\Http\Requests;

use App\Contexts\Users\Infrastructure\Http\Requests\Concerns\ValidatesCompensation;
use App\Shared\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class EditUserRequest extends FormRequest
{
    use ValidatesCompensation;

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
            ...$this->compensationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'branch_id.required' => 'Seleccioná una sucursal.',
            ...$this->compensationMessages(),
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Datos inválidos: ' . implode(', ', $validator->errors()->all()),
                // Detalle por campo para que el modal de Empleados marque cada input.
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
