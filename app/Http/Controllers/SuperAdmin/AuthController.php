<?php

namespace App\Http\Controllers\SuperAdmin;

use App\SuperAdmin;
use App\Franchise;
use App\Services\FranchiseDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Login de Super Admin
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $superAdmin = SuperAdmin::where('email', $request->email)->first();

        if (!$superAdmin || !Hash::check($request->password, $superAdmin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (!$superAdmin->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Cuenta desactivada',
            ], 403);
        }

        $token = $superAdmin->createToken('super-admin-token')->plainTextToken;
        $superAdmin->updateLastLogin();

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => $superAdmin,
                'token' => $token,
                'user_type' => 'super_admin',
            ],
        ]);
    }

    /**
     * Logout de Super Admin
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout exitoso',
        ]);
    }

    /**
     * Obtener perfil del Super Admin
     */
    public function profile(Request $request): JsonResponse
    {
        $superAdmin = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $superAdmin,
                'permissions' => $superAdmin->permissions,
            ],
        ]);
    }

    /**
     * Obtener franquicias disponibles para el Super Admin
     */
    public function getAvailableFranchises(Request $request): JsonResponse
    {
        // Asegurar que siempre usamos la conexión principal
        $franchises = Franchise::on('mysql')->where('is_active', true)
            ->select('id', 'name', 'code', 'logo_path', 'subdomain')
            ->orderBy('name')
            ->get()
            ->map(function ($franchise) {
                return [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'code' => $franchise->code,
                    'logo_url' => $franchise->logo_path ? asset('storage/' . $franchise->logo_path) : null,
                    'url' => $franchise->getFullUrl(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $franchises,
        ]);
    }

    /**
     * Seleccionar franquicia para operar
     */
    public function selectFranchise(Request $request, Franchise $franchise): JsonResponse
    {
        if (!$franchise->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Franquicia no disponible',
            ], 403);
        }

        // Guardar la franquicia seleccionada en la sesión
        session(['current_franchise_id' => $franchise->id]);
        
        // También guardar en el request para que el middleware lo detecte
        $request->merge(['current_franchise_id' => $franchise->id]);

        return response()->json([
            'success' => true,
            'message' => 'Franquicia seleccionada exitosamente',
            'data' => [
                'franchise' => [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'code' => $franchise->code,
                    'database_name' => $franchise->database_name,
                    'primary_color' => $franchise->primary_color ?? '#f97316',
                ],
            ],
        ]);
    }

    /**
     * Obtener franquicia actual
     */
    public function getCurrentFranchise(Request $request): JsonResponse
    {
        $franchiseId = session('current_franchise_id');
        
        if (!$franchiseId) {
            return response()->json([
                'success' => false,
                'message' => 'No hay franquicia seleccionada',
                'data' => null,
            ], 200); // Devolver 200 en lugar de 404 para que no se trate como error crítico
        }

        // Asegurar que siempre usamos la conexión principal
        $franchise = Franchise::on('mysql')->find($franchiseId);

        if (!$franchise || !$franchise->isActive()) {
            // Limpiar la sesión si la franquicia no existe o está inactiva
            session()->forget('current_franchise_id');
            
            return response()->json([
                'success' => false,
                'message' => 'Franquicia no disponible',
                'data' => null,
            ], 200); // Devolver 200 en lugar de 404
        }

        // Construir la URL del logo si existe
        $logoUrl = null;
        if ($franchise->logo_path) {
            $baseUrl = config('app.url', 'http://localhost:8000');
            $logoUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($franchise->logo_path, '/');
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'code' => $franchise->code,
                    'primary_color' => $franchise->primary_color ?? '#f97316',
                    'logo_url' => $logoUrl,
                    'logo_path' => $franchise->logo_path,
                ],
                'access_url' => $franchise->getFullUrl(),
            ],
        ]);
    }

    /**
     * Entrar a una franquicia como usuario normal (autologin)
     * Crea o encuentra un usuario ADMINISTRADOR en la DB de la franquicia para el Super Admin
     */
    public function enterFranchise(Request $request, Franchise $franchise): JsonResponse
    {
        if (!$franchise->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Franquicia no disponible',
            ], 403);
        }

        $superAdmin = $request->user();
        
        if (!$superAdmin instanceof SuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Se requiere Super Admin.',
            ], 403);
        }

        // Guardar la franquicia seleccionada en la sesión
        session(['current_franchise_id' => $franchise->id]);

        try {
            // Usar el servicio para crear o encontrar un usuario en la DB de la franquicia
            $franchiseDatabaseService = app(\App\Services\FranchiseDatabaseService::class);
            
            // Configurar conexión a la DB de la franquicia
            $franchiseDatabaseService->setFranchiseConnection($franchise);
            
            // Buscar o crear un usuario ADMINISTRADOR para el Super Admin en la DB de la franquicia
            // Usar un email único para evitar conflictos: superadmin-{franchise_id}@superadmin.local
            $franchiseUserEmail = "superadmin-{$franchise->id}@superadmin.local";
            
            $franchiseUser = \App\Shared\Models\User::where('email', $franchiseUserEmail)
                ->where('role', \App\Shared\Enums\UserRole::ADMINISTRADOR->value)
                ->first();
            
            if (!$franchiseUser) {
                // Crear usuario ADMINISTRADOR en la DB de la franquicia
                // Usar una contraseña aleatoria (no importa porque el token se genera aquí)
                $franchiseUser = \App\Shared\Models\User::create([
                    'name' => $superAdmin->name . ' (Super Admin)',
                    'email' => $franchiseUserEmail,
                    'password' => bcrypt('super-admin-temp-password-' . $franchise->id), // Contraseña temporal
                    'role' => \App\Shared\Enums\UserRole::ADMINISTRADOR->value,
                    // No incluir franchise_id porque la tabla users en la DB de franquicia no tiene esa columna
                ]);
            }
            
            // Crear token de acceso para este usuario
            $token = $franchiseUser->createToken('franchise-access-' . $franchise->id)->plainTextToken;
            
            // Restaurar conexión principal
            $franchiseDatabaseService->restoreMainConnection();
            
            // Construir la URL del logo si existe
            $logoUrl = null;
            if ($franchise->logo_path) {
                $baseUrl = config('app.url', 'http://localhost:8000');
                $logoUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($franchise->logo_path, '/');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Acceso a franquicia concedido',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $franchiseUser->id,
                        'name' => $franchiseUser->name,
                        'email' => $superAdmin->email, // Usar el email del Super Admin para el frontend
                        'role' => $franchiseUser->role->value,
                        'franchise_id' => $franchise->id, // Para el frontend
                    ],
                    'franchise' => [
                        'id' => $franchise->id,
                        'name' => $franchise->name,
                        'code' => $franchise->code,
                        'primary_color' => $franchise->primary_color ?? '#f97316',
                        'logo_path' => $franchise->logo_path,
                        'logo_url' => $logoUrl,
                    ],
                    'user_type' => 'user', // Tratar como usuario normal para el frontend
                ],
            ]);
            
        } catch (\Exception $e) {
            // Restaurar conexión principal en caso de error
            $franchiseDatabaseService = app(\App\Services\FranchiseDatabaseService::class);
            $franchiseDatabaseService->restoreMainConnection();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al acceder a la franquicia: ' . $e->getMessage(),
            ], 500);
        }
    }
}
