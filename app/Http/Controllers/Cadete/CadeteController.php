<?php

namespace App\Http\Controllers\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\ShipmentLocation;
use App\Shared\Models\Transport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CadeteController extends Controller
{
    /**
     * GET /cadete/profile - Perfil del cadete
     */
    public function profile(): JsonResponse
    {
        $user = Auth::user();
        
        // Obtener el transporte asignado al cadete
        $transport = Transport::where('cadete_id', $user->id)->first();
        
        return response()->json([
            'success' => true,
            'cadete' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'branch_id' => $user->branch_id,
                'transport' => $transport ? [
                    'id' => $transport->id,
                    'plate' => $transport->plate,
                    'description' => $transport->description,
                    'phone' => $transport->phone,
                    'insurance' => $transport->insurance,
                    'usage' => $transport->usage,
                ] : null,
            ]
        ]);
    }

    /**
     * GET /cadete/shipments - Envíos asignados
     */
    public function shipments(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Obtener transportes asignados al cadete
        $transportIds = Transport::where('cadete_id', $user->id)->pluck('id');
        
        if ($transportIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No tienes transportes asignados',
                'shipments' => []
            ]);
        }

        // Filtros opcionales
        $status = $request->get('status');
        $date = $request->get('date');
        
        $query = Commission::with([
            'client:id,name,last_name,phone,address',
            'originLocation:id,name,address,origin,phone,schedule',
            'destinationLocation:id,name,address,origin,phone,schedule',
            'items:id,commission_id,type,size,quantity,detail',
            'transport:id,plate,description'
        ])
        ->whereIn('transport_id', $transportIds);

        // Aplicar filtros
        if ($status) {
            $query->where('status', $status);
        }
        
        if ($date) {
            $query->whereDate('date', $date);
        }

        $shipments = $query->orderBy('date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get()
                          ->map(function ($commission) {
                              return [
                                  'id' => $commission->id,
                                  'tracking_number' => 'RYR' . str_pad($commission->id, 9, '0', STR_PAD_LEFT),
                                  'status' => $commission->status->value,
                                  'status_label' => $this->getStatusLabel($commission->status),
                                  'date' => $commission->date->format('Y-m-d'),
                                  'total' => $commission->total,
                                  'client' => $commission->client ? [
                                      'id' => $commission->client->id,
                                      'name' => $commission->client->name . ' ' . $commission->client->last_name,
                                      'phone' => $commission->client->phone,
                                      'address' => $commission->client->address,
                                  ] : null,
                                  'origin' => $commission->originLocation ? [
                                      'id' => $commission->originLocation->id,
                                      'name' => $commission->originLocation->name,
                                      'address' => $commission->originLocation->address,
                                      'city' => $commission->originLocation->origin,
                                      'phone' => $commission->originLocation->phone,
                                      'schedule' => $commission->originLocation->schedule,
                                  ] : null,
                                  'destination' => $commission->destinationLocation ? [
                                      'id' => $commission->destinationLocation->id,
                                      'name' => $commission->destinationLocation->name,
                                      'address' => $commission->destinationLocation->address,
                                      'city' => $commission->destinationLocation->origin,
                                      'phone' => $commission->destinationLocation->phone,
                                      'schedule' => $commission->destinationLocation->schedule,
                                  ] : null,
                                  'items' => $commission->items->map(function ($item) {
                                      return [
                                          'id' => $item->id,
                                          'type' => $item->type,
                                          'size' => $item->size,
                                          'quantity' => $item->quantity,
                                          'detail' => $item->detail,
                                      ];
                                  }),
                                  'transport' => $commission->transport ? [
                                      'id' => $commission->transport->id,
                                      'plate' => $commission->transport->plate,
                                      'description' => $commission->transport->description,
                                  ] : null,
                              ];
                          });

        return response()->json([
            'success' => true,
            'shipments' => $shipments,
            'total' => $shipments->count(),
            'filters' => [
                'status' => $status,
                'date' => $date,
            ]
        ]);
    }

    /**
     * PUT /cadete/shipments/:id - Actualizar estado de envío
     */
    public function updateShipmentStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pendiente,en_transito,entregado,cancelado',
            'observation' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        
        // Verificar que el cadete tenga acceso a esta comisión
        $transportIds = Transport::where('cadete_id', $user->id)->pluck('id');
        
        $commission = Commission::whereIn('transport_id', $transportIds)
                               ->where('id', $id)
                               ->first();

        if (!$commission) {
            return response()->json([
                'message' => 'Envío no encontrado o no tienes permisos para modificarlo'
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Actualizar estado
            $oldStatus = $commission->status;
            $commission->status = CommissionStatus::from($request->status);
            $commission->save();

            // Registrar log del cambio (opcional - si existe CommissionLog)
            if (class_exists(\App\Shared\Models\CommissionLog::class)) {
                \App\Shared\Models\CommissionLog::create([
                    'commission_id' => $commission->id,
                    'user_id' => $user->id,
                    'action' => 'status_change',
                    'old_value' => $oldStatus->value,
                    'new_value' => $commission->status->value,
                    'observation' => $request->observation,
                    'created_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estado del envío actualizado correctamente',
                'shipment' => [
                    'id' => $commission->id,
                    'status' => $commission->status->value,
                    'status_label' => $this->getStatusLabel($commission->status),
                    'updated_at' => $commission->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar el estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /cadete/shipments/:id/location - Enviar ubicación GPS
     */
    public function sendLocation(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:255',
            'observation' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        
        // Verificar que el cadete tenga acceso a esta comisión
        $transportIds = Transport::where('cadete_id', $user->id)->pluck('id');
        
        $commission = Commission::whereIn('transport_id', $transportIds)
                               ->where('id', $id)
                               ->first();

        if (!$commission) {
            return response()->json([
                'message' => 'Envío no encontrado o no tienes permisos para enviar ubicación'
            ], 404);
        }

        try {
            $location = ShipmentLocation::create([
                'commission_id' => $commission->id,
                'cadete_id' => $user->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'address' => $request->address,
                'observation' => $request->observation,
                'recorded_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ubicación registrada correctamente',
                'location' => [
                    'id' => $location->id,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'address' => $location->address,
                    'observation' => $location->observation,
                    'recorded_at' => $location->recorded_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la ubicación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /cadete/stats - Estadísticas del cadete
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Obtener transportes asignados al cadete
        $transportIds = Transport::where('cadete_id', $user->id)->pluck('id');
        
        if ($transportIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No tienes transportes asignados',
                'stats' => []
            ]);
        }

        // Período (por defecto mes actual)
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $baseQuery = Commission::whereIn('transport_id', $transportIds)
                              ->whereBetween('date', [$startDate, $endDate]);

        // Estadísticas generales
        $totalShipments = $baseQuery->count();
        $totalRevenue = $baseQuery->sum('total');

        // Estadísticas por estado
        $statsByStatus = $baseQuery->select('status', DB::raw('count(*) as count'))
                                  ->groupBy('status')
                                  ->get()
                                  ->pluck('count', 'status');

        // Envíos por día (últimos 7 días)
        $dailyShipments = Commission::whereIn('transport_id', $transportIds)
                                   ->whereBetween('date', [now()->subDays(6)->format('Y-m-d'), now()->format('Y-m-d')])
                                   ->select(DB::raw('DATE(date) as date'), DB::raw('count(*) as count'))
                                   ->groupBy(DB::raw('DATE(date)'))
                                   ->orderBy('date')
                                   ->get();

        // Rendimiento (entregados vs total)
        $deliveredCount = $baseQuery->where('status', CommissionStatus::ENTREGADO)->count();
        $deliveryRate = $totalShipments > 0 ? round(($deliveredCount / $totalShipments) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'stats' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'totals' => [
                    'shipments' => $totalShipments,
                    'revenue' => $totalRevenue,
                    'delivered' => $deliveredCount,
                    'delivery_rate' => $deliveryRate,
                ],
                'by_status' => [
                    'pendiente' => $statsByStatus[CommissionStatus::PENDIENTE->value] ?? 0,
                    'en_transito' => $statsByStatus[CommissionStatus::EN_TRANSITO->value] ?? 0,
                    'entregado' => $statsByStatus[CommissionStatus::ENTREGADO->value] ?? 0,
                    'cancelado' => $statsByStatus[CommissionStatus::CANCELADO->value] ?? 0,
                ],
                'daily_shipments' => $dailyShipments->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'count' => $item->count,
                    ];
                }),
                'transports' => Transport::where('cadete_id', $user->id)
                                        ->select('id', 'plate', 'description')
                                        ->get(),
            ]
        ]);
    }

    /**
     * Obtener etiqueta legible del estado
     */
    private function getStatusLabel(CommissionStatus $status): string
    {
        return match($status) {
            CommissionStatus::PENDIENTE => 'Pendiente',
            CommissionStatus::EN_TRANSITO => 'En Tránsito',
            CommissionStatus::ENTREGADO => 'Entregado',
            CommissionStatus::CANCELADO => 'Cancelado',
            default => $status->value,
        };
    }
}