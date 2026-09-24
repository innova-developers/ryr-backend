<?php

namespace App\Contexts\Users\Infrastructure\Http\Requests;

use App\Contexts\Users\Infrastructure\Http\Requests\Concerns\ValidatesCompensation;
use App\Shared\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class CreateUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', new Enum(UserRole::class)],
            'branch_id' => ['required', 'exists:branches,id'],
            ...$this->compensationRules(),
        ];
    }

    public function messages(): array
    {
        return $this->compensationMessages();
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
