<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Models\Branch;
use App\Shared\Models\BranchLocality;
use App\Shared\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * RC-483 — Localidades que atiende cada sucursal.
 *
 * Determina qué comisiones de OTRAS sucursales entran al pool de esta, sin cambiar
 * la propiedad administrativa: la comisión sigue siendo de la sucursal que la cargó,
 * que conserva seguimiento y visibilidad.
 */
class BranchLocalityController extends Controller
{
    public function index(int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);

        if (! $branch) {
            return response()->json(['success' => false, 'message' => 'Sucursal no encontrada'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'localities' => $branch->localities()->orderBy('locality')->pluck('locality'),
            ],
        ]);
    }

    /**
     * Reemplaza el conjunto de localidades de la sucursal. Se manda la lista completa
     * para que la pantalla no tenga que ir agregando y borrando de a una.
     */
    public function sync(Request $request, int $branchId): JsonResponse
    {
        $branch = Branch::find($branchId);

        if (! $branch) {
            return response()->json(['success' => false, 'message' => 'Sucursal no encontrada'], 404);
        }

        $validated = $request->validate([
            'localities' => 'present|array',
            'localities.*' => 'string|max:255',
        ]);

        $localidades = collect($validated['localities'])
            ->map(fn ($l) => mb_strtoupper(trim((string) $l)))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($branch, $localidades) {
            $branch->localities()->delete();

            foreach ($localidades as $locality) {
                BranchLocality::create([
                    'branch_id' => $branch->id,
                    'locality' => $locality,
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => [
                'branch_id' => $branch->id,
                'localities' => $localidades,
            ],
        ]);
    }

    /**
     * Localidades existentes en el sistema, para poblar el selector.
     */
    public function available(Request $request): JsonResponse
    {
        $search = $request->query('q', '');

        $query = Location::query()
            ->whereNotNull('origin')
            ->where('origin', '!=', '');

        if ($search !== '') {
            $query->where('origin', 'LIKE', '%' . $search . '%');
        }

        $localidades = $query->selectRaw('UPPER(TRIM(origin)) as locality')
            ->distinct()
            ->orderBy('locality')
            ->limit(200)
            ->pluck('locality');

        return response()->json(['success' => true, 'data' => $localidades]);
    }
}
