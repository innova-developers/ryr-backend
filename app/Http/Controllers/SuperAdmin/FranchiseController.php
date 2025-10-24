<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Franchise;
use App\Services\FranchiseDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class FranchiseController
{
    protected $franchiseDatabaseService;

    public function __construct(FranchiseDatabaseService $franchiseDatabaseService)
    {
        $this->franchiseDatabaseService = $franchiseDatabaseService;
    }

    /**
     * Listar todas las franquicias
     */
    public function index(Request $request): JsonResponse
    {
        $query = Franchise::query();

        // Filtros
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $franchises = $query->orderBy('created_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $franchises,
        ]);
    }

    /**
     * Crear nueva franquicia
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:franchises,code',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Crear la franquicia con datos automáticos
            $franchise = new Franchise($request->all());
            $franchise->subdomain = $franchise->generateSubdomain();
            $franchise->database_name = $franchise->generateDatabaseName();
            $franchise->save();

            // Crear la base de datos y usuario administrador
            $dbResult = $this->franchiseDatabaseService->createFranchiseDatabase($franchise);
            
            if (!$dbResult['success']) {
                $franchise->delete();
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating franchise database: ' . $dbResult['error'],
                ], 500);
            }

            // Activar la franquicia
            $franchise->activate();

            return response()->json([
                'success' => true,
                'message' => 'Franchise created successfully',
                'data' => [
                    'franchise' => $franchise,
                    'admin_credentials' => $dbResult['admin_credentials'],
                    'franchise_url' => $franchise->getFullUrl(),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating franchise: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar franquicia específica
     */
    public function show(Franchise $franchise): JsonResponse
    {
        $stats = $this->franchiseDatabaseService->getFranchiseDatabaseStats($franchise);

        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => $franchise,
                'database_stats' => $stats,
            ],
        ]);
    }

    /**
     * Actualizar franquicia
     */
    public function update(Request $request, Franchise $franchise): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:franchises,code,' . $franchise->id,
            'domain' => 'nullable|string|max:255|unique:franchises,domain,' . $franchise->id,
            'subdomain' => 'nullable|string|max:100|unique:franchises,subdomain,' . $franchise->id,
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'contact_person' => 'nullable|string|max:255',
            'settings' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $franchise->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Franchise updated successfully',
                'data' => $franchise,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating franchise: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Activar franquicia
     */
    public function activate(Franchise $franchise): JsonResponse
    {
        try {
            $franchise->activate();

            return response()->json([
                'success' => true,
                'message' => 'Franchise activated successfully',
                'data' => $franchise,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error activating franchise: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Desactivar franquicia
     */
    public function deactivate(Franchise $franchise): JsonResponse
    {
        try {
            $franchise->deactivate();

            return response()->json([
                'success' => true,
                'message' => 'Franchise deactivated successfully',
                'data' => $franchise,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deactivating franchise: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar franquicia
     */
    public function destroy(Franchise $franchise): JsonResponse
    {
        try {
            // Eliminar la base de datos de la franquicia
            $this->franchiseDatabaseService->dropFranchiseDatabase($franchise);
            
            // Eliminar el registro de la franquicia
            $franchise->delete();

            return response()->json([
                'success' => true,
                'message' => 'Franchise deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting franchise: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de una franquicia
     */
    public function stats(Franchise $franchise): JsonResponse
    {
        try {
            $stats = $this->franchiseDatabaseService->getFranchiseDatabaseStats($franchise);

            return response()->json([
                'success' => true,
                'data' => [
                    'franchise' => $franchise,
                    'stats' => $stats,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting franchise stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Acceder a una franquicia específica
     */
    public function access(Franchise $franchise): JsonResponse
    {
        if (!$franchise->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Franchise is not active',
            ], 403);
        }

        // Guardar la franquicia en la sesión
        session(['current_franchise_id' => $franchise->id]);

        return response()->json([
            'success' => true,
            'message' => 'Access granted to franchise',
            'data' => [
                'franchise' => $franchise,
                'access_url' => $franchise->getFullUrl(),
            ],
        ]);
    }

    /**
     * Subir logo de la franquicia
     */
    public function uploadLogo(Request $request, Franchise $franchise): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $logoPath = $request->file('logo')->store('franchises/logos', 'public');
            
            $franchise->update([
                'logo_path' => $logoPath,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'logo_url' => asset('storage/' . $logoPath),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener configuración de la franquicia
     */
    public function getSettings(Franchise $franchise): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'franchise' => $franchise,
                'settings' => $franchise->settings ?? [],
            ],
        ]);
    }

    /**
     * Actualizar configuración de la franquicia
     */
    public function updateSettings(Request $request, Franchise $franchise): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $franchise->update([
                'settings' => $request->get('settings'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings updated successfully',
                'data' => $franchise,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las franquicias para selector
     */
    public function getFranchisesForSelector(): JsonResponse
    {
        $franchises = Franchise::where('is_active', true)
            ->whereNotNull('activated_at')
            ->select('id', 'name', 'code', 'logo_path')
            ->orderBy('name')
            ->get()
            ->map(function ($franchise) {
                return [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'code' => $franchise->code,
                    'logo_url' => $franchise->logo_path ? asset('storage/' . $franchise->logo_path) : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $franchises,
        ]);
    }
}
