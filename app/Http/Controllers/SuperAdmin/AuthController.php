<?php

namespace App\Http\Controllers\SuperAdmin;

use App\SuperAdmin;
use App\Franchise;
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
        $franchises = Franchise::where('is_active', true)
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

        return response()->json([
            'success' => true,
            'message' => 'Franquicia seleccionada exitosamente',
            'data' => [
                'franchise' => $franchise,
                'access_url' => $franchise->getFullUrl(),
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
            ], 404);
        }

        $franchise = Franchise::find($franchiseId);

        if (!$franchise || !$franchise->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Franquicia no disponible',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => $franchise,
                'access_url' => $franchise->getFullUrl(),
            ],
        ]);
    }
}
