<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Shared\Models\Commission;
use App\Shared\Models\User;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controller;

class CommissionCadeteController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }
    /**
     * Asociar un cadete a una comisión
     */
    public function assignCadete(Request $request, Commission $commission): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cadete_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::in(User::whereIn('role', [UserRole::CADETE, UserRole::CADETE_EXTERNO])->pluck('id'))
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Nota: Ahora se permite asignar cadetes en cualquier estado de la comisión

        // Verificar que el cadete no esté ya asignado
        if ($commission->cadete_id === (int) $request->cadete_id) {
            return response()->json([
                'message' => 'Este cadete ya está asignado a la comisión'
            ], 400);
        }

        // Actualizar la comisión
        $commission->update([
            'cadete_id' => $request->cadete_id,
            'status' => CommissionStatus::CADETE_ASIGNADO
        ]);

        // Crear notificación para el cadete
        $this->notificationService->createCommissionAssignedNotification($commission);

        return response()->json([
            'message' => 'Cadete asignado exitosamente',
            'commission' => $commission->fresh(['cadete']),
            'cadete' => $commission->cadete
        ], 200);
    }

    /**
     * Desasociar un cadete de una comisión
     */
    public function unassignCadete(Request $request, Commission $commission): JsonResponse
    {
        $currentUser = $request->user();

        // Verificar que la comisión tenga un cadete asignado
        if (!$commission->cadete_id) {
            return response()->json([
                'message' => 'Esta comisión no tiene un cadete asignado'
            ], 400);
        }

        // Si el usuario es un cadete, solo puede desasignarse a sí mismo
        if ($currentUser && in_array($currentUser->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO])) {
            if ($commission->cadete_id !== $currentUser->id) {
                return response()->json([
                    'message' => 'No autorizado. Solo puedes desasignarte de tus propias comisiones.'
                ], 403);
            }
        }

        // Nota: Ahora se permite desasignar cadetes en cualquier estado de la comisión

        $previousCadeteId = $commission->cadete_id;

        // Actualizar la comisión
        $commission->update([
            'cadete_id' => null,
            'status' => CommissionStatus::BUSCANDO_CADETE
        ]);

        return response()->json([
            'message' => 'Cadete desasignado exitosamente',
            'commission' => $commission->fresh(),
            'previous_cadete_id' => $previousCadeteId
        ], 200);
    }

    /**
     * Cambiar el cadete asignado a una comisión
     */
    public function changeCadete(Request $request, Commission $commission): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cadete_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::in(User::whereIn('role', [UserRole::CADETE, UserRole::CADETE_EXTERNO])->pluck('id'))
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Nota: Ahora se permite cambiar cadetes en cualquier estado de la comisión

        // Verificar que no sea el mismo cadete
        if ($commission->cadete_id === (int) $request->cadete_id) {
            return response()->json([
                'message' => 'La comisión ya tiene asignado este cadete'
            ], 400);
        }

        $previousCadeteId = $commission->cadete_id;

        // Actualizar la comisión
        $commission->update([
            'cadete_id' => $request->cadete_id,
            'status' => CommissionStatus::CADETE_ASIGNADO
        ]);

        // Crear notificación para el nuevo cadete
        $this->notificationService->createCommissionAssignedNotification($commission);

        return response()->json([
            'message' => 'Cadete cambiado exitosamente',
            'commission' => $commission->fresh(['cadete']),
            'previous_cadete_id' => $previousCadeteId,
            'new_cadete' => $commission->cadete
        ], 200);
    }

    /**
     * Obtener el cadete asignado a una comisión
     */
    public function getAssignedCadete(Commission $commission): JsonResponse
    {
        if (!$commission->cadete_id) {
            return response()->json([
                'message' => 'Esta comisión no tiene un cadete asignado',
                'cadete' => null
            ], 200);
        }

        return response()->json([
            'cadete' => $commission->cadete,
            'assigned_at' => $commission->updated_at
        ], 200);
    }

    /**
     * Obtener todas las comisiones asignadas a un cadete específico
     */
    public function getCommissionsByCadete(Request $request, User $cadete): JsonResponse
    {
        $currentUser = $request->user();
        
        // Verificar que el usuario sea un cadete
        if (!in_array($cadete->role->value, ['cadete', 'cadete_externo'])) {
            return response()->json([
                'message' => 'El usuario especificado no es un cadete',
                'user_role' => $cadete->role->value
            ], 400);
        }

        // Si el usuario actual es un cadete, solo puede ver sus propias comisiones
        if ($currentUser && in_array($currentUser->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO])) {
            if ($currentUser->id !== $cadete->id) {
                return response()->json([
                    'message' => 'No autorizado. Solo puedes ver tus propias comisiones.',
                ], 403);
            }
        }

        $commissions = Commission::where('cadete_id', $cadete->id)
            ->with(['client', 'destination', 'branch', 'items', 'deliverySignature'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'cadete' => $cadete,
            'commissions' => $commissions,
            'total' => $commissions->count()
        ], 200);
    }

    /**
     * Obtener comisiones disponibles (sin cadete asignado) - Pool de comisiones
     */
    public function getAvailableCommissions(Request $request): JsonResponse
    {
        try {
            // Validar filtros opcionales
            $validated = $request->validate([
                'status' => 'nullable|string',
                'branch_id' => 'nullable|integer|exists:branches,id',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            // Query base: comisiones sin cadete asignado
            $query = Commission::whereNull('cadete_id')
                ->where('status', '!=', CommissionStatus::CANCELADO)
                ->where('status', '!=', CommissionStatus::ENTREGADO)
                ->where('status', '!=', CommissionStatus::DEVUELTO_REMITENTE);

            // Aplicar filtros opcionales
            if (isset($validated['status'])) {
                try {
                    $status = CommissionStatus::from($validated['status']);
                    $query->where('status', $status);
                } catch (\ValueError $e) {
                    // Si el status no es válido, ignorar el filtro
                }
            }

            if (isset($validated['branch_id'])) {
                $query->where('branch_id', $validated['branch_id']);
            }

            if (isset($validated['date_from'])) {
                $query->whereDate('date', '>=', $validated['date_from']);
            }

            if (isset($validated['date_to'])) {
                $query->whereDate('date', '<=', $validated['date_to']);
            }

            // Cargar relaciones necesarias
            $commissions = $query->with([
                'client',
                'destination',
                'branch',
                'originLocation',
                'destinationLocation',
                'items',
                'user'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

            // Formatear respuesta
            $formattedCommissions = $commissions->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'tracking_number' => $commission->id,
                    'status' => $commission->status->value,
                    'status_label' => $commission->status->getCadeteStatus(),
                    'date' => $commission->date ? $commission->date->format('Y-m-d') : null,
                    'total' => $commission->total,
                    'notes' => $commission->notes,
                    'client' => $commission->client ? [
                        'id' => $commission->client->id,
                        'name' => $commission->client->name . ' ' . $commission->client->last_name,
                        'phone' => $commission->client->phone,
                        'email' => $commission->client->email,
                    ] : null,
                    'origin' => $commission->originLocation ? [
                        'id' => $commission->originLocation->id,
                        'name' => $commission->originLocation->name,
                        'address' => $commission->originLocation->address,
                        'phone' => $commission->originLocation->phone,
                        'city' => $commission->originLocation->origin ?? null,
                    ] : null,
                    'destination' => $commission->destinationLocation ? [
                        'id' => $commission->destinationLocation->id,
                        'name' => $commission->destinationLocation->name,
                        'address' => $commission->destinationLocation->address,
                        'phone' => $commission->destinationLocation->phone,
                        'city' => $commission->destinationLocation->origin ?? null,
                    ] : null,
                    'branch' => $commission->branch ? [
                        'id' => $commission->branch->id,
                        'name' => $commission->branch->name,
                    ] : null,
                    'items' => $commission->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'type' => $item->type?->value,
                            'size' => $item->size?->value,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'subtotal' => $item->subtotal,
                            'detail' => $item->detail,
                        ];
                    }),
                    'created_at' => $commission->created_at?->toISOString(),
                    'updated_at' => $commission->updated_at?->toISOString(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedCommissions,
                'total' => $formattedCommissions->count(),
                'filters' => $validated,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comisiones disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Autoasignar una comisión al cadete autenticado
     */
    public function selfAssignCommission(Request $request, Commission $commission): JsonResponse
    {
        try {
            $currentUser = $request->user();

            // Verificar que el usuario autenticado sea un cadete
            if (!$currentUser || !in_array($currentUser->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo los cadetes pueden autoasignarse comisiones'
                ], 403);
            }

            // Verificar que la comisión no tenga cadete asignado
            if ($commission->cadete_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta comisión ya tiene un cadete asignado',
                    'assigned_cadete_id' => $commission->cadete_id
                ], 400);
            }

            // Verificar que la comisión no esté cancelada o en estado final
            if (in_array($commission->status, [
                CommissionStatus::CANCELADO,
                CommissionStatus::ENTREGADO,
                CommissionStatus::DEVUELTO_REMITENTE
            ])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar una comisión en estado final',
                    'status' => $commission->status->value
                ], 400);
            }

            // Asignar la comisión al cadete autenticado
            $commission->update([
                'cadete_id' => $currentUser->id,
                'status' => CommissionStatus::CADETE_ASIGNADO
            ]);

            // Crear notificación para el cadete
            $this->notificationService->createCommissionAssignedNotification($commission);

            // Cargar relaciones para la respuesta
            $commission->load(['client', 'destination', 'branch', 'originLocation', 'destinationLocation', 'items']);

            return response()->json([
                'success' => true,
                'message' => 'Comisión asignada exitosamente',
                'commission' => [
                    'id' => $commission->id,
                    'status' => $commission->status->value,
                    'status_label' => $commission->status->getCadeteStatus(),
                    'cadete_id' => $commission->cadete_id,
                    'client' => $commission->client ? [
                        'id' => $commission->client->id,
                        'name' => $commission->client->name . ' ' . $commission->client->last_name,
                    ] : null,
                    'origin' => $commission->originLocation ? [
                        'id' => $commission->originLocation->id,
                        'name' => $commission->originLocation->name,
                        'address' => $commission->originLocation->address,
                    ] : null,
                    'destination' => $commission->destinationLocation ? [
                        'id' => $commission->destinationLocation->id,
                        'name' => $commission->destinationLocation->name,
                        'address' => $commission->destinationLocation->address,
                    ] : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al autoasignar la comisión',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
