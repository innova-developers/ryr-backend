<?php

namespace App\Http\Controllers\SuperAdmin;

use App\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController
{
    /**
     * Listar todos los Super Admins
     */
    public function index(Request $request): JsonResponse
    {
        $query = SuperAdmin::query();

        // Filtros opcionales
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $superAdmins = $query->orderBy('created_at', 'desc')
                            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $superAdmins,
        ]);
    }

    /**
     * Mostrar un Super Admin específico
     */
    public function show(SuperAdmin $superAdmin): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $superAdmin,
        ]);
    }

    /**
     * Crear un nuevo Super Admin
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:super_admins,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
        ]);

        $superAdmin = SuperAdmin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'is_active' => $request->get('is_active', true),
            'permissions' => $request->get('permissions', []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Super Admin creado exitosamente',
            'data' => $superAdmin,
        ], 201);
    }

    /**
     * Actualizar un Super Admin
     */
    public function update(Request $request, SuperAdmin $superAdmin): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:super_admins,email,' . $superAdmin->id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
        ]);

        $updateData = $request->only(['name', 'email', 'phone', 'is_active', 'permissions']);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $superAdmin->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Super Admin actualizado exitosamente',
            'data' => $superAdmin,
        ]);
    }

    /**
     * Eliminar un Super Admin
     */
    public function destroy(SuperAdmin $superAdmin): JsonResponse
    {
        $superAdmin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Super Admin eliminado exitosamente',
        ]);
    }

    /**
     * Activar/desactivar Super Admin
     */
    public function toggleStatus(SuperAdmin $superAdmin): JsonResponse
    {
        $superAdmin->update(['is_active' => !$superAdmin->is_active]);

        $message = $superAdmin->is_active ? 'Super Admin activado' : 'Super Admin desactivado';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $superAdmin,
        ]);
    }

    /**
     * Obtener estadísticas de Super Admins
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_super_admins' => SuperAdmin::count(),
            'active_super_admins' => SuperAdmin::where('is_active', true)->count(),
            'inactive_super_admins' => SuperAdmin::where('is_active', false)->count(),
            'recent_super_admins' => SuperAdmin::orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'email', 'is_active', 'created_at']),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
