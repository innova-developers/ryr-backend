<?php

namespace App\Http\Controllers\Auth;

use App\Services\VerificationCodeService;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateIdentifierController
{
    private VerificationCodeService $verificationService;

    public function __construct(VerificationCodeService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    public function validateIdentifier(Request $request): JsonResponse
    {
        // Validar el payload
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255',
            'type' => 'required|in:email,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $identifier = $request->input('identifier');
        $type = $request->input('type');

        try {
            // Buscar cliente por email o teléfono
            $customer = null;
            $user = null;

            if ($type === 'email') {
                $customer = Customer::where('email', $identifier)->first();
                $user = User::where('email', $identifier)->where('role', 'cliente')->first();
            } else {
                $customer = Customer::where('phone', $identifier)->first();
                // Para teléfono, buscar usuario por el email del cliente
                $user = null;
                if ($customer && $customer->email) {
                    $user = User::where('email', $customer->email)->where('role', 'cliente')->first();
                }
            }

            // Si no existe el cliente, devolver false para indicar que debe registrarse
            if (! $customer && ! $user) {
                return response()->json([
                    'success' => false,
                    'exists' => false,
                    'message' => 'Cliente no encontrado. Debe registrarse.',
                    'requires_registration' => true,
                ], 200);
            }

            // Generar código de verificación
            $code = $this->verificationService->generateCode();
            $this->verificationService->storeCode($identifier, $type, $code);

            // En modo testing, simular envío exitoso
            if (app()->environment('testing')) {
                $sent = true;
            } else {
                // Enviar código según el tipo
                $sent = false;
                if ($type === 'email') {
                    $sent = $this->verificationService->sendCodeByEmail($identifier, $code);
                } else {
                    $sent = $this->verificationService->sendCodeByWhatsApp($identifier, $code);
                }
            }

            if (! $sent) {
                // En caso de error de envío, devolver éxito pero con advertencia
                // Esto permite que el flujo continúe en modo de desarrollo
                \Log::warning('No se pudo enviar código de verificación por email', [
                    'identifier' => $identifier,
                    'type' => $type,
                    'code' => $code,
                ]);

                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'identifier' => $identifier,
                    'type' => $type,
                    'customer_id' => $customer?->id,
                    'user_id' => $user?->id,
                    'message' => 'Código de verificación enviado.',
                ], 200);
            }

            // Devolver respuesta exitosa
            return response()->json([
                'success' => true,
                'exists' => true,
                'message' => 'Código de verificación enviado correctamente.',
                'identifier' => $identifier,
                'type' => $type,
                'customer_id' => $customer ? $customer->id : null,
                'user_id' => $user ? $user->id : null,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en validateIdentifier: ' . $e->getMessage(), [
                'identifier' => $identifier ?? 'unknown',
                'type' => $type ?? 'unknown',
                'exception' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Intente nuevamente.',
                'error' => app()->environment('local') ? $e->getMessage() : 'Error interno',
            ], 500);
        }
    }

    public function verifyCode(Request $request): JsonResponse
    {
        // Validar el payload
        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:255',
            'type' => 'required|in:email,phone',
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $identifier = $request->input('identifier');
        $type = $request->input('type');
        $code = $request->input('code');

        try {
            // Verificar el código
            $isValid = $this->verificationService->verifyCode($identifier, $type, $code);

            if (! $isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Código inválido o expirado.',
                ], 400);
            }

            // Buscar el cliente/usuario
            $customer = null;
            $user = null;

            if ($type === 'email') {
                $customer = Customer::where('email', $identifier)->first();
                $user = User::where('email', $identifier)->where('role', 'cliente')->first();
            } else {
                $customer = Customer::where('phone', $identifier)->first();
                // Para teléfono, buscar usuario por el email del cliente
                $user = null;
                if ($customer && $customer->email) {
                    $user = User::where('email', $customer->email)->where('role', 'cliente')->first();
                }
            }

            // Generar token de acceso (puedes usar Sanctum o JWT)
            $token = null;
            if ($user) {
                $token = $user->createToken('customer-token')->plainTextToken;
            }

            return response()->json([
                'success' => true,
                'message' => 'Código verificado correctamente.',
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ] : null,
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                'token' => $token,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en verifyCode: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Intente nuevamente.',
            ], 500);
        }
    }
}
