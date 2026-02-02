<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Franchise;
use App\Services\FranchiseDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        // Asegurar que siempre usamos la conexión principal
        $query = Franchise::on('mysql');

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

        // Agregar logo_url a cada franquicia
        $franchises->getCollection()->transform(function ($franchise) {
            if ($franchise->logo_path) {
                // Construir la URL completa del logo
                $baseUrl = config('app.url', 'http://localhost:8000');
                $franchise->logo_url = rtrim($baseUrl, '/') . '/storage/' . ltrim($franchise->logo_path, '/');
            } else {
                $franchise->logo_url = null;
            }
            return $franchise;
        });

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
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
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
            
            // Agregar logo_url a la respuesta
            if ($franchise->logo_path) {
                $baseUrl = config('app.url', 'http://localhost:8000');
                $franchise->logo_url = rtrim($baseUrl, '/') . '/storage/' . ltrim($franchise->logo_path, '/');
            } else {
                $franchise->logo_url = null;
            }

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
        
        // Asegurar que usamos la conexión principal
        $franchise = Franchise::on('mysql')->findOrFail($franchise->id);
        
        // Agregar logo_url a la respuesta
        $franchise->logo_url = $franchise->logo_path ? asset('storage/' . $franchise->logo_path) : null;

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
            'primary_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
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
            // Asegurar que usamos la conexión principal
            $franchise = Franchise::on('mysql')->findOrFail($franchise->id);
            
            $franchise->update($request->all());
            
            // Refrescar para obtener los datos actualizados
            $franchise->refresh();
            
            // Agregar logo_url a la respuesta
            if ($franchise->logo_path) {
                $baseUrl = config('app.url', 'http://localhost:8000');
                $franchise->logo_url = rtrim($baseUrl, '/') . '/storage/' . ltrim($franchise->logo_path, '/');
            } else {
                $franchise->logo_url = null;
            }

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
        try {
            // Asegurar que usamos la conexión principal para actualizar la franquicia
            $franchise = Franchise::on('mysql')->findOrFail($franchise->id);
            
            Log::info('Upload logo request received', [
                'franchise_id' => $franchise->id,
                'has_file' => $request->hasFile('logo'),
                'all_files' => $request->allFiles(),
            ]);
            
            $validator = Validator::make($request->all(), [
                'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                Log::warning('Logo validation failed', [
                    'franchise_id' => $franchise->id,
                    'errors' => $validator->errors()->toArray(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Verificar que el archivo existe
            if (!$request->hasFile('logo')) {
                Log::warning('No file received in uploadLogo', ['franchise_id' => $franchise->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió ningún archivo',
                ], 422);
            }

            $file = $request->file('logo');
            
            Log::info('File received', [
                'franchise_id' => $franchise->id,
                'file_size' => $file->getSize(),
                'file_mime' => $file->getMimeType(),
                'file_name' => $file->getClientOriginalName(),
            ]);
            
            // Validar tamaño del archivo (2MB = 2048KB)
            if ($file->getSize() > 2048 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo es demasiado grande. El tamaño máximo es 2MB',
                ], 422);
            }

            // Verificar que el directorio existe, si no crearlo
            $directory = storage_path('app/public/franchises/logos');
            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
                Log::info('Created directory', ['directory' => $directory]);
            }

            // Guardar el archivo usando Storage
            $logoPath = Storage::disk('public')->putFile('franchises/logos', $file);
            
            if (!$logoPath) {
                throw new \Exception('No se pudo guardar el archivo en el almacenamiento');
            }
            
            Log::info('File stored', [
                'franchise_id' => $franchise->id,
                'logo_path' => $logoPath,
            ]);
            
            // Actualizar la franquicia usando la conexión principal
            $franchise->update([
                'logo_path' => $logoPath,
            ]);

            Log::info('Logo uploaded successfully', [
                'franchise_id' => $franchise->id,
                'logo_path' => $logoPath,
            ]);

            // Construir la URL completa del logo
            $baseUrl = config('app.url', 'http://localhost:8000');
            $logoUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($logoPath, '/');
            
            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'logo_url' => $logoUrl,
                    'logo_path' => $logoPath,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error uploading logo', [
                'franchise_id' => $franchise->id ?? null,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error uploading logo', [
                'franchise_id' => $franchise->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error uploading logo: ' . $e->getMessage(),
                'error_details' => config('app.debug') ? $e->getTraceAsString() : null,
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
        // Asegurar que siempre usamos la conexión principal
        $franchises = Franchise::on('mysql')->where('is_active', true)
            ->whereNotNull('activated_at')
            ->select('id', 'name', 'code', 'logo_path', 'subdomain')
            ->orderBy('name')
            ->get()
            ->map(function ($franchise) {
                return [
                    'id' => $franchise->id,
                    'name' => $franchise->name,
                    'code' => $franchise->code,
                    'subdomain' => $franchise->subdomain,
                    'logo_url' => $franchise->logo_path 
                        ? rtrim(config('app.url', 'http://localhost:8000'), '/') . '/storage/' . ltrim($franchise->logo_path, '/')
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $franchises,
        ]);
    }
}
