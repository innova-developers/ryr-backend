<?php

namespace App\Http\Controllers\Admin;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CollectionPoolController
{
    /**
     * Obtiene el listado de clientes con deuda (saldo cuenta corriente < 0)
     * Incluye las comisiones históricas del cliente
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verificar que el usuario tenga uno de los roles permitidos
            $allowedRoles = [UserRole::ADMINISTRADOR, UserRole::COBRADOR, UserRole::MOSTRADOR];
            if (!in_array($user->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este recurso',
                ], 403);
            }

            // Obtener parámetros de filtrado
            $search = $request->input('search'); // Búsqueda por nombre, apellido, DNI, email
            $city = $request->input('city');
            $minDebtAmount = $request->input('min_debt_amount'); // Monto mínimo de deuda
            $maxDebtAmount = $request->input('max_debt_amount'); // Monto máximo de deuda
            $branchId = $request->input('branch_id');
            $assigned = $request->input('assigned'); // null = todos, true = solo asignados, false = solo no asignados
            $internalUserId = $request->input('internal_user_id'); // Filtrar por usuario interno específico
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $sortBy = $request->input('sort_by', 'balance'); // balance, name, city, assigned
            $sortDirection = $request->input('sort_direction', 'asc'); // asc, desc

            // Filtrar por sucursal según el rol del usuario
            if ($user->branch_id && in_array($user->role, [UserRole::MOSTRADOR, UserRole::COBRADOR])) {
                $branchId = $user->branch_id;
            }

            // Obtener todos los clientes y calcular su saldo
            // Solo considerar transacciones con estado OK para el cálculo del saldo
            // Verificar si la columna status existe antes de filtrar
            $hasStatusColumn = \Schema::hasColumn('current_accounts', 'status');
            
            $customersWithBalance = Customer::select('customers.*')
                ->selectSub(function ($query) use ($hasStatusColumn) {
                    $subquery = $query->select('balance')
                        ->from('current_accounts')
                        ->whereColumn('current_accounts.customer_id', 'customers.id');
                    
                    // Solo filtrar por status si la columna existe
                    if ($hasStatusColumn) {
                        $subquery->where('status', CurrentAccountStatus::OK->value);
                    }
                    
                    $subquery->orderBy('transaction_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->limit(1);
                }, 'current_balance')
                ->havingRaw('COALESCE(current_balance, 0) < 0')
                ->get();

            // Obtener IDs de clientes con deuda
            $customerIds = $customersWithBalance->pluck('id')->toArray();

            if (empty($customerIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron clientes con deuda',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $perPage,
                        'total' => 0,
                        'from' => null,
                        'to' => null,
                    ],
                ], 200);
            }

            // Query base para obtener clientes con deuda
            $query = Customer::whereIn('id', $customerIds)
                ->with(['branch:id,name', 'internalUser:id,name,email']);

            // Aplicar filtros
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('last_name', 'LIKE', "%{$search}%")
                      ->orWhere('dni', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('mobile', 'LIKE', "%{$search}%");
                });
            }

            if ($city) {
                $query->where('city', 'LIKE', "%{$city}%");
            }

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            // Filtrar por asignación de usuario interno
            if ($assigned !== null) {
                if ($assigned === 'true' || $assigned === true || $assigned === '1' || $assigned === 1) {
                    // Solo clientes asignados
                    $query->whereNotNull('internal_user_id');
                } else {
                    // Solo clientes no asignados
                    $query->whereNull('internal_user_id');
                }
            }

            // Filtrar por usuario interno específico
            if ($internalUserId) {
                $query->where('internal_user_id', $internalUserId);
            }

            // Aplicar filtros de monto de deuda
            // Filtrar por monto mínimo de deuda (el saldo debe ser <= -minDebtAmount)
            if ($minDebtAmount !== null) {
                $filteredCustomerIds = $customersWithBalance
                    ->filter(function ($customer) use ($minDebtAmount) {
                        return abs($customer->current_balance) >= abs($minDebtAmount);
                    })
                    ->pluck('id')
                    ->toArray();
                $query->whereIn('id', $filteredCustomerIds);
            }

            // Filtrar por monto máximo de deuda (el saldo debe ser >= -maxDebtAmount)
            if ($maxDebtAmount !== null) {
                $filteredCustomerIds = $customersWithBalance
                    ->filter(function ($customer) use ($maxDebtAmount) {
                        return abs($customer->current_balance) <= abs($maxDebtAmount);
                    })
                    ->pluck('id')
                    ->toArray();
                $query->whereIn('id', $filteredCustomerIds);
            }

            // Ordenamiento
            switch ($sortBy) {
                case 'name':
                    $query->orderBy('name', $sortDirection)
                          ->orderBy('last_name', $sortDirection);
                    break;
                case 'city':
                    $query->orderBy('city', $sortDirection);
                    break;
                case 'assigned':
                    // Ordenar por asignación: primero los asignados, luego los no asignados
                    $query->orderByRaw('CASE WHEN internal_user_id IS NULL THEN 1 ELSE 0 END ' . ($sortDirection === 'asc' ? 'ASC' : 'DESC'))
                          ->orderBy('name', 'asc');
                    break;
                case 'balance':
                default:
                    // Ordenar por balance usando los datos de customersWithBalance
                    $sortedIds = $customersWithBalance
                        ->sortBy('current_balance', SORT_REGULAR, $sortDirection === 'desc')
                        ->pluck('id')
                        ->toArray();
                    if (!empty($sortedIds)) {
                        $query->orderByRaw('FIELD(id, ' . implode(',', $sortedIds) . ')');
                    }
                    break;
            }

            // Paginación
            $customers = $query->paginate($perPage, ['*'], 'page', $page);

            // Crear un mapa de balances para acceso rápido
            $balanceMap = $customersWithBalance->keyBy('id');

            // Calcular estadísticas antes de transformar
            $allCustomersWithBalance = Customer::whereIn('id', $customerIds)
                ->select('id', 'internal_user_id')
                ->get();
            
            $totalAssigned = $allCustomersWithBalance->whereNotNull('internal_user_id')->count();
            $totalUnassigned = $allCustomersWithBalance->whereNull('internal_user_id')->count();
            $totalDebtAmount = $customersWithBalance->sum(function ($customer) {
                return abs($customer->current_balance ?? 0);
            });

            // Verificar si la columna type existe en la tabla commissions
            $hasTypeColumn = Schema::hasColumn('commissions', 'type');
            
            // Cargar comisiones históricas para cada cliente
            $customers->getCollection()->transform(function ($customer) use ($balanceMap, $hasStatusColumn, $hasTypeColumn) {
                $balance = $balanceMap->get($customer->id)?->current_balance ?? 0;
                
                // Calcular total de movimientos pendientes de confirmar
                $pendingAmount = 0;
                if ($hasStatusColumn) {
                    $pendingTransactions = CurrentAccount::where('customer_id', $customer->id)
                        ->where('status', CurrentAccountStatus::PENDIENTE->value)
                        ->where('type', 'credit') // Solo créditos pendientes
                        ->sum('amount');
                    $pendingAmount = (float) $pendingTransactions;
                }
                
                // Obtener solo comisiones ORDINARIAS o EXTRAORDINARIAS con total > 0 y estado PAGO_VALIDACION
                $commissionsQuery = Commission::with([
                    'items:id,commission_id,type,size,quantity,detail,unit_price,subtotal',
                    'destination:id,origin,destination,fixed_price',
                    'originLocation:id,name,address,origin,phone',
                    'destinationLocation:id,name,address,origin,phone',
                    'branch:id,name',
                ])
                ->where('client_id', $customer->id)
                ->where('status', CommissionStatus::PAGO_VALIDACION->value)
                ->where('total', '>', 0);
                
                // Solo filtrar por tipo si la columna existe
                if ($hasTypeColumn) {
                    $commissionsQuery->whereIn('type', [CommissionType::ORDINARIA->value, CommissionType::EXTRAORDINARIA->value]);
                }
                
                $commissions = $commissionsQuery
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($commission) {
                    return [
                        'id' => $commission->id,
                        'client_id' => $commission->client_id,
                        'date' => $commission->date->format('Y-m-d'),
                        'status' => $commission->status->value,
                        'status_label' => $commission->status->getAdminStatus(),
                        'type' => $commission->type?->value ?? null,
                        'type_label' => $commission->type?->label() ?? null,
                        'total' => (float) $commission->total,
                        'payment_method' => $commission->payment_method?->value,
                        'payment_method_label' => $commission->payment_method_label,
                        'origin' => $commission->destination?->origin,
                        'destination' => $commission->destination?->destination,
                        'origin_location_id' => $commission->origin_location_id,
                        'destination_location_id' => $commission->destination_location_id,
                        'origin_location' => $commission->originLocation ? [
                            'id' => $commission->originLocation->id,
                            'name' => $commission->originLocation->name,
                            'address' => $commission->originLocation->address,
                            'city' => $commission->originLocation->origin,
                            'phone' => $commission->originLocation->phone,
                        ] : null,
                        'destination_location' => $commission->destinationLocation ? [
                            'id' => $commission->destinationLocation->id,
                            'name' => $commission->destinationLocation->name,
                            'address' => $commission->destinationLocation->address,
                            'city' => $commission->destinationLocation->origin,
                            'phone' => $commission->destinationLocation->phone,
                        ] : null,
                        'items' => $commission->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'type' => $item->type,
                                'size' => $item->size,
                                'quantity' => $item->quantity,
                                'detail' => $item->detail,
                                'unit_price' => (float) $item->unit_price,
                                'subtotal' => (float) $item->subtotal,
                            ];
                        }),
                        'branch' => $commission->branch ? [
                            'id' => $commission->branch->id,
                            'name' => $commission->branch->name,
                        ] : null,
                        'notes' => $commission->notes,
                        'created_at' => $commission->created_at->toISOString(),
                        'updated_at' => $commission->updated_at->toISOString(),
                    ];
                });

                return [
                    'id' => $customer->id,
                    'dni' => $customer->dni,
                    'name' => $customer->name,
                    'last_name' => $customer->last_name,
                    'full_name' => $customer->full_name,
                    'email' => $customer->email,
                    'mobile' => $customer->mobile,
                    'phone' => $customer->phone,
                    'address' => $customer->address,
                    'city' => $customer->city,
                    'branch' => $customer->branch ? [
                        'id' => $customer->branch->id,
                        'name' => $customer->branch->name,
                    ] : null,
                    'internal_user_id' => $customer->internal_user_id,
                    'is_assigned' => !is_null($customer->internal_user_id), // Indica si tiene usuario interno asignado
                    'internal_user' => $customer->internalUser ? [
                        'id' => $customer->internalUser->id,
                        'name' => $customer->internalUser->name,
                        'email' => $customer->internalUser->email,
                    ] : null,
                    'current_balance' => (float) $balance,
                    'debt_amount' => abs((float) $balance), // Monto de deuda (positivo)
                    'pending' => $pendingAmount, // Total de movimientos pendientes de confirmar
                    'commissions' => $commissions,
                    'commissions_count' => $commissions->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Clientes con deuda obtenidos correctamente',
                'data' => $customers->items(),
                'meta' => [
                    'current_page' => $customers->currentPage(),
                    'last_page' => $customers->lastPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'from' => $customers->firstItem(),
                    'to' => $customers->lastItem(),
                ],
                'statistics' => [
                    'total_customers_with_debt' => count($customerIds),
                    'assigned_customers' => $totalAssigned,
                    'unassigned_customers' => $totalUnassigned,
                    'total_debt_amount' => (float) $totalDebtAmount,
                    'assigned_percentage' => count($customerIds) > 0 
                        ? round(($totalAssigned / count($customerIds)) * 100, 2) 
                        : 0,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes con deuda',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene los datos de un cliente específico con deuda
     * Mantiene la misma estructura de datos que el endpoint index
     */
    public function show(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verificar que el usuario tenga uno de los roles permitidos
            $allowedRoles = [UserRole::ADMINISTRADOR, UserRole::COBRADOR, UserRole::MOSTRADOR];
            if (!in_array($user->role, $allowedRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para acceder a este recurso',
                ], 403);
            }

            // Verificar si la columna status existe antes de filtrar
            $hasStatusColumn = \Schema::hasColumn('current_accounts', 'status');
            
            // Obtener el cliente y calcular su saldo
            $customerWithBalance = Customer::select('customers.*')
                ->selectSub(function ($query) use ($hasStatusColumn) {
                    $subquery = $query->select('balance')
                        ->from('current_accounts')
                        ->whereColumn('current_accounts.customer_id', 'customers.id');
                    
                    // Solo filtrar por status si la columna existe
                    if ($hasStatusColumn) {
                        $subquery->where('status', CurrentAccountStatus::OK->value);
                    }
                    
                    $subquery->orderBy('transaction_date', 'desc')
                        ->orderBy('id', 'desc')
                        ->limit(1);
                }, 'current_balance')
                ->where('customers.id', $id)
                ->first();

            if (!$customerWithBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Verificar si tiene deuda (aunque el endpoint puede devolver el cliente aunque no tenga deuda)
            $balance = $customerWithBalance->current_balance ?? 0;

            // Cargar relaciones necesarias
            $customer = Customer::with(['branch:id,name', 'internalUser:id,name,email'])
                ->find($id);

            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado',
                ], 404);
            }

            // Calcular total de movimientos pendientes de confirmar
            $pendingAmount = 0;
            if ($hasStatusColumn) {
                $pendingTransactions = CurrentAccount::where('customer_id', $customer->id)
                    ->where('status', CurrentAccountStatus::PENDIENTE->value)
                    ->where('type', 'credit') // Solo créditos pendientes
                    ->sum('amount');
                $pendingAmount = (float) $pendingTransactions;
            }

            // Verificar si la columna type existe en la tabla commissions
            $hasTypeColumn = Schema::hasColumn('commissions', 'type');
            
            // Obtener solo comisiones ORDINARIAS o EXTRAORDINARIAS con total > 0 y estado PAGO_VALIDACION
            $commissionsQuery = Commission::with([
                'items:id,commission_id,type,size,quantity,detail,unit_price,subtotal',
                'destination:id,origin,destination,fixed_price',
                'originLocation:id,name,address,origin,phone',
                'destinationLocation:id,name,address,origin,phone',
                'branch:id,name',
            ])
            ->where('client_id', $customer->id)
            ->where('status', CommissionStatus::PAGO_VALIDACION->value)
            ->where('total', '>', 0);
            
            // Solo filtrar por tipo si la columna existe
            if ($hasTypeColumn) {
                $commissionsQuery->whereIn('type', [CommissionType::ORDINARIA->value, CommissionType::EXTRAORDINARIA->value]);
            }
            
            $commissions = $commissionsQuery
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($commission) {
                    return [
                        'id' => $commission->id,
                        'client_id' => $commission->client_id,
                        'date' => $commission->date->format('Y-m-d'),
                        'status' => $commission->status->value,
                        'status_label' => $commission->status->getAdminStatus(),
                        'type' => $commission->type?->value ?? null,
                        'type_label' => $commission->type?->label() ?? null,
                        'total' => (float) $commission->total,
                        'payment_method' => $commission->payment_method?->value,
                        'payment_method_label' => $commission->payment_method_label,
                        'origin' => $commission->destination?->origin,
                        'destination' => $commission->destination?->destination,
                        'origin_location_id' => $commission->origin_location_id,
                        'destination_location_id' => $commission->destination_location_id,
                        'origin_location' => $commission->originLocation ? [
                            'id' => $commission->originLocation->id,
                            'name' => $commission->originLocation->name,
                            'address' => $commission->originLocation->address,
                            'city' => $commission->originLocation->origin,
                            'phone' => $commission->originLocation->phone,
                        ] : null,
                        'destination_location' => $commission->destinationLocation ? [
                            'id' => $commission->destinationLocation->id,
                            'name' => $commission->destinationLocation->name,
                            'address' => $commission->destinationLocation->address,
                            'city' => $commission->destinationLocation->origin,
                            'phone' => $commission->destinationLocation->phone,
                        ] : null,
                        'items' => $commission->items->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'type' => $item->type,
                                'size' => $item->size,
                                'quantity' => $item->quantity,
                                'detail' => $item->detail,
                                'unit_price' => (float) $item->unit_price,
                                'subtotal' => (float) $item->subtotal,
                            ];
                        }),
                        'branch' => $commission->branch ? [
                            'id' => $commission->branch->id,
                            'name' => $commission->branch->name,
                        ] : null,
                        'notes' => $commission->notes,
                        'created_at' => $commission->created_at->toISOString(),
                        'updated_at' => $commission->updated_at->toISOString(),
                    ];
                });

            // Construir la respuesta con la misma estructura que index
            $data = [
                'id' => $customer->id,
                'dni' => $customer->dni,
                'name' => $customer->name,
                'last_name' => $customer->last_name,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'mobile' => $customer->mobile,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'city' => $customer->city,
                'branch' => $customer->branch ? [
                    'id' => $customer->branch->id,
                    'name' => $customer->branch->name,
                ] : null,
                'internal_user_id' => $customer->internal_user_id,
                'is_assigned' => !is_null($customer->internal_user_id),
                'internal_user' => $customer->internalUser ? [
                    'id' => $customer->internalUser->id,
                    'name' => $customer->internalUser->name,
                    'email' => $customer->internalUser->email,
                ] : null,
                'current_balance' => (float) $balance,
                'debt_amount' => abs((float) $balance),
                'pending' => $pendingAmount,
                'commissions' => $commissions,
                'commissions_count' => $commissions->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Cliente obtenido correctamente',
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el cliente',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

