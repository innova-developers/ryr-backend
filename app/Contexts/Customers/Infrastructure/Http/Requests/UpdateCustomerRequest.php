<?php

namespace App\Contexts\Customers\Infrastructure\Http\Requests;

use App\Services\CustomerPortalUserService;
use App\Shared\Enums\CustomerType;
use App\Shared\Enums\IvaStatus;
use App\Shared\Models\Customer;
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
        $rules = [
            'type' => ['nullable', Rule::in(CustomerType::values())],
            'iva_status' => ['nullable', Rule::in(IvaStatus::values())],
            'email' => ['nullable', 'email', $this->emailDisponible(...)],
        ];

        // Empresa requiere razón social + CUIT.
        if ($this->input('type') === CustomerType::COMPANY->value) {
            $rules['razon_social'] = ['required', 'string', 'max:255'];
            $rules['cuit'] = ['required', 'string', 'max:13'];
        }

        return $rules;
    }

    /**
     * RC-518: antes era Rule::unique('customers', 'email'), que cuenta también a los
     * clientes dados de baja. El cliente NICOLAS RODRIGUEZ (DNI 32756426) no pudo pasar
     * de cliente-267@ryrcomisiones.com a nicolasrg27@gmail.com porque ese email lo
     * retenía "test nico", un cliente de prueba borrado en febrero: el admin recibía
     * "The email has already been taken" sin forma de ver quién lo tenía. Ahora sólo
     * bloquean los clientes vivos y los accesos al portal de otro cliente, y el mensaje
     * dice cuál es.
     */
    private function emailDisponible(string $attribute, mixed $value, \Closure $fail): void
    {
        $customer = Customer::find($this->route('id'));

        if (! $customer || ! is_string($value) || strcasecmp($value, (string) $customer->email) === 0) {
            return;
        }

        $conflicto = app(CustomerPortalUserService::class)->emailConflict($customer, $value);

        if ($conflicto) {
            $fail($conflicto);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        // errors por campo para que el form marque el input exacto en vez de adivinar
        // el campo parseando el mensaje.
        throw new HttpResponseException(
            response()->json([
                'message' => 'Datos inválidos: ' . implode(', ', $validator->errors()->all()),
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
