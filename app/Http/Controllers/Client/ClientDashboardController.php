<?php

namespace App\Http\Controllers\Client;

use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ClientDashboardController
{
    /**
     * Resuelve el cliente detrás del usuario logueado.
     *
     * Primero por customers.user_id, que es el vínculo real que crea el alta de
     * cliente. Se mantiene el match por email/teléfono como respaldo porque en la
     * base histórica hay 2931 clientes cuyo user_id apunta al administrador que los
     * cargó, no al usuario del cliente; para esos, el vínculo fuerte no existe.
     */
    private function resolveCustomer($user): ?Customer
    {
        $customer = Customer::where('user_id', $user->id)->first();

        if ($customer) {
            return $customer;
        }

        return Customer::where(function ($q) use ($user) {
            $q->where('email', $user->email)
              ->orWhere('mobile', $user->email);

            if (! empty($user->phone)) {
                $q->orWhere('phone', $user->phone);
            }
        })->first();
    }

    public function getProfile(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            // Verificar que el usuario tenga rol de cliente
            if ($user->role !== UserRole::CLIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
                ], 403);
            }

            // Buscar el cliente asociado al usuario
            $customer = $this->resolveCustomer($user);

            return response()->json([
                'success' => true,
                'customer' => $customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ] : null,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en getProfile: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            // Verificar que el usuario tenga rol de cliente
            if ($user->role !== UserRole::CLIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
                ], 403);
            }

            // Validar los datos
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos inválidos',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Buscar el cliente asociado al usuario
            $customer = $this->resolveCustomer($user);

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Actualizar datos del cliente
            $customer->update([
                'name' => $request->input('name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
            ]);

            // Actualizar email del usuario si cambió
            if ($user->email !== $request->input('email')) {
                $user->update(['email' => $request->input('email')]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente',
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en updateProfile: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function getShipments(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            // Verificar que el usuario tenga rol de cliente
            if ($user->role !== UserRole::CLIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
                ], 403);
            }

            // Buscar el cliente asociado al usuario
            $customer = $this->resolveCustomer($user);

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Obtener las comisiones (envíos) del cliente
            $commissions = Commission::with(['items', 'client', 'destination', 'deliverySignature'])
                ->where('client_id', $customer->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $shipments = $commissions->map(function ($commission) {
                return [
                    'id' => $commission->id,
                    'tracking_number' => $commission->id,
                    'status' => $commission->status,
                    'origin' => $commission->originLocation ? $commission->originLocation->name : null,
                    'destination' => $commission->destinationLocation ? $commission->destinationLocation->name : null,
                    'total' => $commission->total ?? 0,
                    'created_at' => $commission->created_at ? $commission->created_at->toISOString() : null,
                    'items' => $commission->items ? $commission->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'type' => $item->type ?? null,
                            'size' => $item->size ?? null,
                            'quantity' => $item->quantity ?? 0,
                            'unit_price' => $item->unit_price ?? 0,
                            'subtotal' => $item->subtotal ?? 0,
                        ];
                    })->toArray() : [],
                    'signature_data' => $commission->deliverySignature ? [
                        'receiver_name' => $commission->deliverySignature->receiver_name,
                        'receiver_phone' => $commission->deliverySignature->receiver_phone,
                        'notes' => $commission->deliverySignature->notes,
                        'delivery_timestamp' => $commission->deliverySignature->delivery_timestamp->toISOString(),
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'shipments' => $shipments,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en getShipments: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function getAccountBalance(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            // Verificar que el usuario tenga rol de cliente
            if ($user->role !== UserRole::CLIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
                ], 403);
            }

            // Buscar el cliente asociado al usuario
            $customer = $this->resolveCustomer($user);

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Obtener el saldo actual de cuenta corriente (última transacción con estado OK)
            $lastTransaction = CurrentAccount::where('customer_id', $customer->id)
                ->where('status', \App\Shared\Enums\CurrentAccountStatus::OK->value)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $balance = $lastTransaction ? $lastTransaction->balance : 0;

            return response()->json([
                'success' => true,
                'balance' => $balance,
                'currency' => 'ARS',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en getAccountBalance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function getCurrentAccountTransactions(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            // Verificar que el usuario tenga rol de cliente
            if ($user->role !== UserRole::CLIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
                ], 403);
            }

            // Buscar el cliente asociado al usuario
            $customer = $this->resolveCustomer($user);

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Obtener las transacciones de cuenta corriente del cliente
            $transactions = CurrentAccount::where('customer_id', $customer->id)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $formattedTransactions = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'description' => $transaction->description,
                    'amount' => $transaction->amount,
                    'balance' => $transaction->balance,
                    'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->toISOString() : null,
                    'created_at' => $transaction->created_at ? $transaction->created_at->toISOString() : null,
                ];
            });

            return response()->json([
                'success' => true,
                'transactions' => $formattedTransactions,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en getCurrentAccountTransactions: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }

    public function getCurrentAccountBalance(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado',
                ], 401);
            }

            // Verificar que el usuario tenga rol de cliente
            if ($user->role !== UserRole::CLIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo clientes pueden acceder a este recurso.',
                ], 403);
            }

            // Buscar el cliente asociado al usuario
            $customer = $this->resolveCustomer($user);

            if (! $customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Obtener el saldo actual de cuenta corriente (solo transacciones con estado OK)
            $lastTransaction = CurrentAccount::where('customer_id', $customer->id)
                ->where('status', \App\Shared\Enums\CurrentAccountStatus::OK->value)
                ->orderBy('transaction_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $balance = $lastTransaction ? $lastTransaction->balance : 0;

            return response()->json([
                'success' => true,
                'balance' => $balance,
                'currency' => 'ARS',
                'last_transaction_date' => $lastTransaction ? $lastTransaction->transaction_date->toISOString() : null,
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en getCurrentAccountBalance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
            ], 500);
        }
    }
}
