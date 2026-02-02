<?php

namespace App\Http\Controllers\Admin;

use App\Franchise;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FranchiseController
{
    /**
     * Obtener información de la franquicia actual para usuarios normales
     */
    public function getCurrentFranchise(Request $request): JsonResponse
    {
        // Intentar obtener la franquicia desde el request (agregada por el middleware)
        $franchise = $request->get('current_franchise');
        
        // Si no está en el request, intentar obtenerla desde la sesión
        if (!$franchise) {
            $franchiseId = session('current_franchise_id');
            if ($franchiseId) {
                $franchise = Franchise::on('mysql')->where('id', $franchiseId)
                    ->where('is_active', true)
                    ->first();
            }
        }
        
        // Si aún no hay franquicia, intentar obtenerla desde parámetros de la petición o headers
        if (!$franchise) {
            $franchiseIdFromRequest = $request->get('franchise_id') 
                ?? $request->header('X-Franchise-Id')
                ?? ($request->user() && isset($request->user()->franchise_id) ? $request->user()->franchise_id : null);
            
            if ($franchiseIdFromRequest) {
                $franchise = Franchise::on('mysql')->where('id', $franchiseIdFromRequest)
                    ->where('is_active', true)
                    ->first();
            }
        }
        
        if (!$franchise) {
            return response()->json([
                'success' => false,
                'message' => 'No hay franquicia seleccionada',
                'data' => null,
            ], 200);
        }
        
        // Construir la URL del logo si existe
        $logoUrl = null;
        if ($franchise->logo_path) {
            $baseUrl = config('app.url', 'http://localhost:8000');
            $logoUrl = rtrim($baseUrl, '/') . '/storage/' . ltrim($franchise->logo_path, '/');
        }
        
        // Guardar en la sesión para futuras peticiones
        session(['current_franchise_id' => $franchise->id]);
        
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
            ],
        ]);
    }
}
