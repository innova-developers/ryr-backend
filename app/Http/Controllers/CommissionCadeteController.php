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
    public function unassignCadete(Commission $commission): JsonResponse
    {
        // Verificar que la comisión tenga un cadete asignado
        if (!$commission->cadete_id) {
            return response()->json([
                'message' => 'Esta comisión no tiene un cadete asignado'
            ], 400);
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
        // Verificar que el usuario sea un cadete
        if (!in_array($cadete->role->value, ['cadete', 'cadete_externo'])) {
            return response()->json([
                'message' => 'El usuario especificado no es un cadete',
                'user_role' => $cadete->role->value
            ], 400);
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
}
