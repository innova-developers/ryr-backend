<?php

namespace App\Http\Controllers\Auth;

use App\Services\CustomerPortalUserService;
use App\Services\VerificationCodeService;
use App\Shared\Models\Customer;
use App\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateIdentifierController
{
    private VerificationCodeService $verificationService;
    private CustomerPortalUserService $portalUsers;

    public function __construct(VerificationCodeService $verificationService, CustomerPortalUserService $portalUsers)
    {
        $this->verificationService = $verificationService;
        $this->portalUsers = $portalUsers;
    }

    /**
     * RC-518 (devuelta por QA el 16/09): NICOLAS RODRIGUEZ entraba con su email real,
     * nicolasrg27@gmail.com, y el portal "no cargaba". Ese email era de "test nico",
     * un usuario de prueba cuyo cliente se borró en febrero: verify-code respondía
     * success con customer: null, el front guardaba webCustomer = "null" y el
     * dashboard lo rebotaba a la landing como sesión inválida. Un usuario de portal
     * sin cliente vivo no tiene nada que ver, así que se lo frena con un mensaje.
     */
    private const SIN_CLIENTE_ACTIVO = 'Este email no está asociado a ningún cliente activo de R&R. Comunicate con nosotros para actualizar tus datos.';

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

            if (! $customer && $user) {
                $customer = $this->portalUsers->customerOf($user);
            }

            // No mandar un código que después no lleva a ningún lado.
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'exists' => true,
                    'requires_registration' => false,
                    'message' => self::SIN_CLIENTE_ACTIVO,
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
                // Para teléfono, el usuario de portal del cliente: por su email o, si no,
                // por customers.user_id.
                $user = $customer ? $this->portalUsers->portalUserOf($customer) : null;
            }

            if (! $customer && $user) {
                $customer = $this->portalUsers->customerOf($user);
            }

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => self::SIN_CLIENTE_ACTIVO,
                ], 422);
            }

            // RC-518: "no funciona el inicio de sesión suponiendo que el código es
            // correcto; el envío se hace pero no carga". El código llegaba y se validaba
            // bien, pero la respuesta salía con success: true y token: null cuando el
            // cliente no tenía usuario de portal, así que el front no tenía con qué
            // entrar. Sólo 520 de los 3.451 clientes con email tenían usuario: el resto
            // viene de la migración del sistema viejo, que creó el cliente y no el
            // usuario. El código verificado ya prueba que es el dueño del identificador,
            // así que acá se le da de alta el acceso en vez de dejarlo afuera.
            if (! $user) {
                $user = $this->provisionarUsuarioDePortal($customer);
            }

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pudimos habilitar el acceso al portal. Comunicate con R&R.',
                ], 422);
            }

            $token = $user->createToken('customer-token')->plainTextToken;

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

    /**
     * Da de alta el usuario de portal del cliente que todavía no lo tiene.
     *
     * Misma convención de contraseña que usa el alta de clientes desde el admin —DNI
     * para personas, dígitos del CUIT para empresas— así que un cliente aprovisionado
     * acá puede después entrar también por usuario y contraseña.
     */
    private function provisionarUsuarioDePortal(Customer $customer): ?User
    {
        if (! $customer->email) {
            return null;
        }

        // Puede existir el usuario con otro rol (un empleado que además es cliente):
        // en ese caso no se toca, porque el email es único en users.
        $existente = User::where('email', $customer->email)->first();

        if ($existente) {
            return $existente->role === \App\Shared\Enums\UserRole::CLIENTE ? $existente : null;
        }

        $password = $customer->cuit
            ? (preg_replace('/\D/', '', (string) $customer->cuit) ?: 'empresa')
            : (string) ($customer->dni ?? '');

        if ($password === '') {
            $password = \Illuminate\Support\Str::random(12);
        }

        $user = User::create([
            'name' => $this->portalUsers->portalName($customer),
            'email' => $customer->email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => \App\Shared\Enums\UserRole::CLIENTE->value,
            'branch_id' => $customer->branch_id,
        ]);

        // El portal resuelve el cliente por el email del usuario, pero dejar el vínculo
        // explícito evita que un cambio de email lo desconecte. Antes sólo se vinculaba
        // si user_id estaba vacío, y en los clientes migrados apunta al administrador
        // (así quedó AGROSUM, customer 107, al aprovisionarse el 11/09): se vincula
        // siempre, porque acá se llega sólo si el cliente no tenía usuario de portal.
        $this->portalUsers->link($customer, $user);

        \Log::info('Usuario de portal creado al verificar el código', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
        ]);

        return $user;
    }
}
