<?php

namespace App\Http\Controllers\Public;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\Customer;
use App\Shared\Models\Destination;
use App\Shared\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicCommissionController
{
    public function store(Request $request): JsonResponse
    {
        try {
            // Si viene FormData con commission como JSON string, decodificarlo
            $commissionData = $request->all();
            if ($request->has('commission') && is_string($request->input('commission'))) {
                $commissionData = array_merge($commissionData, json_decode($request->input('commission'), true));
            }

            // Forzar el estado a SOLICITUD_RECIBIDA para comisiones públicas
            $commissionData['status'] = CommissionStatus::SOLICITUD_RECIBIDA->value;

            // Validar el payload
            $validator = Validator::make($commissionData, [
                'client_id' => 'required|integer|exists:customers,id',
                'user_id' => 'nullable|integer|exists:users,id',
                'date' => 'required|date',
                'origin' => 'required|string|max:255',
                'destination' => 'required|string|max:255',
                'origin_location_id' => 'required|integer|exists:locations,id',
                'destination_location_id' => 'required|integer|exists:locations,id',
                'a_cuenta' => 'boolean',
                'items' => 'nullable|array',
                'items.*.type' => 'required|string|max:255',
                'items.*.size' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.subtotal' => 'required|numeric|min:0',
                'total' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Verificar que el cliente existe
            $customer = Customer::find($commissionData['client_id']);
            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Verificar que las ubicaciones existen
            $originLocation = Location::find($commissionData['origin_location_id']);
            $destinationLocation = Location::find($commissionData['destination_location_id']);

            if (! $originLocation || ! $destinationLocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ubicación de origen o destino no encontrada',
                ], 404);
            }

            // Buscar o crear el destino
            $destination = Destination::where('origin', $commissionData['origin'])
                ->where('destination', $commissionData['destination'])
                ->first();

            if (! $destination) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ruta de origen a destino no encontrada',
                ], 404);
            }

            // Crear la comisión en una transacción
            $commission = DB::transaction(function () use ($commissionData, $destination, $customer) {
                // Buscar el user_id basándose en el email del cliente
                $user = null;
                if ($customer->email) {
                    $user = \App\Shared\Models\User::where('email', $customer->email)
                        ->where('role', 'cliente')
                        ->first();
                }

                // Crear la comisión
                $commission = Commission::create([
                    'client_id' => $commissionData['client_id'],
                    'destination_id' => $destination->id,
                    'branch_id' => 1, // Sucursal por defecto
                    'user_id' => $commissionData['user_id'] ?? ($user ? $user->id : null), // Priorizar user_id del payload, luego el encontrado por email
                    'origin_location_id' => $commissionData['origin_location_id'],
                    'destination_location_id' => $commissionData['destination_location_id'],
                    'date' => $commissionData['date'],
                    'status' => CommissionStatus::from($commissionData['status']),
                    'total' => $commissionData['total'],
                    'notes' => $commissionData['notes'] ?? null,
                ]);

                // Crear los items de la comisión si existen
                if (isset($commissionData['items']) && !empty($commissionData['items'])) {
                    foreach ($commissionData['items'] as $itemData) {
                        CommissionItem::create([
                            'commission_id' => $commission->id,
                            'type' => $itemData['type'],
                            'size' => $itemData['size'],
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'subtotal' => $itemData['subtotal'],
                        ]);
                    }
                }

                return $commission;
            });

            return response()->json([
                'success' => true,
                'message' => 'Comisión creada correctamente',
                'commission' => [
                    'id' => $commission->id,
                    'tracking_number' => $commission->id,
                    'status' => $commission->status->value,
                    'total' => $commission->total,
                    'date' => $commission->date,
                    'origin' => $commissionData['origin'],
                    'destination' => $commissionData['destination'],
                    'items_count' => count($commissionData['items'] ?? []),
                    'notes' => $commission->notes,
                ],
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error en PublicCommissionController@store: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Intente nuevamente.',
            ], 500);
        }
    }
}
