<?php

namespace App\Http\Controllers\Public;

use App\Contexts\Users\Application\CreateUserUseCase;
use App\Contexts\Users\Application\DTO\CreateUserDTO;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PublicCustomerController
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = app(UserRepository::class);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            // Validar el payload
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:customers,email',
                'phone' => 'nullable|string|max:255',
                'mobile' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:255',
                'city' => 'nullable|string|max:255',
                'dni' => 'required|string|max:255|unique:customers,dni',
                'maps_url' => 'nullable|string|max:255',
                'business_hours' => 'nullable|string|max:255',
                'observations' => 'nullable|string',
                'is_premium' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Verificar que el DNI no esté duplicado
            $existingCustomer = Customer::where('dni', $request->input('dni'))->first();
            if ($existingCustomer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un cliente con ese DNI',
                    'errors' => ['dni' => ['El DNI ya está registrado']],
                ], 422);
            }

            // Verificar que el email no esté duplicado
            $existingCustomerByEmail = Customer::where('email', $request->input('email'))->first();
            if ($existingCustomerByEmail) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya existe un cliente con ese email',
                    'errors' => ['email' => ['El email ya está registrado']],
                ], 422);
            }

            // Crear usuario primero
            $useCaseCreateUser = new CreateUserUseCase($this->userRepository);
            $dtoCreateUser = new CreateUserDTO(
                $request->input('name'),
                $request->input('email'),
                Hash::make('temp_password_' . time()), // Password temporal ya que se autentica con código
                'cliente',
                null, // branch_id null (sin sucursal)
                null, // base_salary
                null, // income_percentage
                null, // commission_percentage
                'fixed_salary' // contract_type por defecto
            );
            $userCreated = $useCaseCreateUser($dtoCreateUser);

            // Crear el cliente
            $customer = Customer::create([
                'dni' => $request->input('dni'),
                'cuit' => $request->input('cuit'),
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'mobile' => $request->input('mobile') ?: $request->input('phone'), // Usar phone como mobile si no se proporciona mobile
                'email' => $request->input('email'),
                'address' => $request->input('address'),
                'city' => $request->input('city'),
                'phone' => $request->input('phone'),
                'maps_url' => $request->input('maps_url'),
                'business_hours' => $request->input('business_hours'),
                'observations' => $request->input('observations'),
                'is_premium' => $request->input('is_premium', false),
                'user_id' => $userCreated->id, // Asignar el usuario creado
                'branch_id' => null, // No se asigna sucursal automáticamente
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente y usuario creados correctamente',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'mobile' => $customer->mobile,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'city' => $customer->city,
                    'dni' => $customer->dni,
                ],
                'user' => [
                    'id' => $userCreated->id,
                    'name' => $userCreated->name,
                    'email' => $userCreated->email,
                    'role' => $userCreated->role,
                ],
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error en PublicCustomerController@store: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Intente nuevamente.',
            ], 500);
        }
    }
}
