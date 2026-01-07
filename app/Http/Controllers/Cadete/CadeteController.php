<?php

namespace App\Http\Controllers\Cadete;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CommissionType;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\ShipmentLocation;
use App\Shared\Models\Transport;
use App\Shared\Models\User;
use App\Shared\Enums\UserRole;
use App\DeliverySignature;
use App\Services\NominatimService;
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
        
        // Cargar la relación con la sucursal
        $user->load('branch');
        
        // Obtener el transporte asignado al cadete
        $transport = Transport::where('cadete_id', $user->id)->first();
        
        // Obtener estadísticas básicas del cadete
        $totalCommissions = Commission::where('cadete_id', $user->id)->count();
        $completedCommissions = Commission::where('cadete_id', $user->id)
                                          ->where('status', CommissionStatus::ENTREGADO)
                                          ->count();
        
        return response()->json([
            'success' => true,
            'cadete' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $this->getRoleLabel($user->role->value),
                'branch_id' => $user->branch_id,
                
                // Información de contratación
                'contract_type' => $user->contract_type ?? 'fixed_salary',
                'contract_type_label' => $this->getContractTypeLabel($user->contract_type ?? 'fixed_salary'),
                'base_salary' => $user->base_salary,
                'commission_percentage' => $user->commission_percentage,
                'income_percentage' => $user->income_percentage,
                
                // Información de la sucursal
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'address' => $user->branch->address,
                    'phone' => $user->branch->phone,
                    'secondary_phone' => $user->branch->secondary_phone,
                    'schedule' => $user->branch->schedule,
                ] : null,
                
                // Información del transporte
                'transport' => $transport ? [
                    'id' => $transport->id,
                    'plate' => $transport->plate,
                    'description' => $transport->description,
                    'phone' => $transport->phone,
                    'insurance' => $transport->insurance,
                    'usage' => $transport->usage,
                ] : null,
                
                // Estadísticas básicas
                'stats' => [
                    'total_commissions' => $totalCommissions,
                    'completed_commissions' => $completedCommissions,
                    'success_rate' => $totalCommissions > 0 ? round(($completedCommissions / $totalCommissions) * 100, 1) : 0,
                ],
                
                // Información del sistema
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'last_login' => $user->last_login_at ?? null,
            ]
        ]);
    }

    /**
     * PUT /cadete/profile - Actualizar perfil del cadete
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Validar los datos de entrada
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20|nullable',
        ]);
        
        // Actualizar solo los campos proporcionados
        $user->fill($validated);
        $user->save();
        
        // Cargar la relación con la sucursal para la respuesta
        $user->load('branch');
        
        // Obtener el transporte asignado al cadete
        $transport = Transport::where('cadete_id', $user->id)->first();
        
        // Obtener estadísticas básicas del cadete
        $totalCommissions = Commission::where('cadete_id', $user->id)->count();
        $completedCommissions = Commission::where('cadete_id', $user->id)
                                          ->where('status', CommissionStatus::ENTREGADO)
                                          ->count();
        
        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado exitosamente',
            'cadete' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $this->getRoleLabel($user->role->value),
                
                // Información de contratación
                'contract_type' => $user->contract_type ?? 'fixed_salary',
                'contract_type_label' => $this->getContractTypeLabel($user->contract_type ?? 'fixed_salary'),
                'base_salary' => $user->base_salary,
                'commission_percentage' => $user->commission_percentage,
                'income_percentage' => $user->income_percentage,
                
                // Información de la sucursal
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'address' => $user->branch->address,
                    'phone' => $user->branch->phone,
                    'secondary_phone' => $user->branch->secondary_phone,
                    'schedule' => $user->branch->schedule,
                ] : null,
                
                // Información del transporte
                'transport' => $transport ? [
                    'id' => $transport->id,
                    'plate' => $transport->plate,
                    'description' => $transport->description,
                    'phone' => $transport->phone,
                    'insurance' => $transport->insurance,
                    'usage' => $transport->usage,
                ] : null,
                
                // Estadísticas básicas
                'stats' => [
                    'total_commissions' => $totalCommissions,
                    'completed_commissions' => $completedCommissions,
                    'success_rate' => $totalCommissions > 0 ? round(($completedCommissions / $totalCommissions) * 100, 1) : 0,
                ],
                
                // Información del sistema
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
                'last_login' => $user->last_login_at ?? null,
            ]
        ]);
    }

    /**
     * PUT /cadete/deliveries/:id - Actualizar estado de envío
     */
    public function updateShipmentStatus(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|string|in:' . implode(',', CommissionStatus::getValidCadeteStatuses()),
                'observation' => 'nullable|string|max:1000',
                // Campos de firma opcionales cuando se marca como entregado
                'receiver_name' => 'nullable|string|max:255',
                'receiver_phone' => 'nullable|string|max:20',
                'notes' => 'nullable|string|max:1000',
                'signature_image' => 'nullable|string', // Base64 PNG
                'delivery_timestamp' => 'nullable|date',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Cadete intentó actualizar estado con valor inválido', [
                'cadete_id' => Auth::id(),
                'commission_id' => $id,
                'status_requested' => $request->status,
                'errors' => $e->errors(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Estado no válido. Estados válidos: ' . implode(', ', CommissionStatus::getValidCadeteStatuses()),
                'errors' => $e->errors(),
                'valid_statuses' => CommissionStatus::getValidCadeteStatuses()
            ], 422);
        }

        $user = Auth::user();
        
        // Verificar que el cadete tenga acceso a esta comisión
        $commission = Commission::where('cadete_id', $user->id)
                               ->where('id', $id)
                               ->first();

        if (!$commission) {
            \Log::warning('Cadete intentó acceder a comisión no autorizada', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Envío no encontrado o no tienes permisos para modificarlo'
            ], 404);
        }

        try {
            // Log del estado anterior
            $oldStatus = $commission->status;
            $oldStatusValue = $oldStatus->value;
            $oldStatusLabel = $oldStatus->getCadeteStatus();
            
            // Guardar el tipo de comisión antes de actualizar
            $commissionType = $commission->type;
            
            // Convertir el estado del cadete al estado administrativo
            $adminStatus = CommissionStatus::fromCadeteStatus($request->status);
            $newStatusValue = $adminStatus->value;
            $newStatusLabel = $adminStatus->getCadeteStatus();
            
            // Log del cambio de estado
            \Log::info('Cadete actualizando estado de comisión', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'old_status' => [
                    'value' => $oldStatusValue,
                    'label' => $oldStatusLabel
                ],
                'new_status' => [
                    'value' => $newStatusValue,
                    'label' => $newStatusLabel,
                    'requested_status' => $request->status
                ],
                'observation' => $request->observation,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);
            
            // Usar el UseCase para actualizar el estado (incluye notificaciones automáticas)
            $useCase = app(\App\Contexts\Commissions\Application\UpdateCommissionStatusUseCase::class);
            $details = 'Estado actualizado por cadete: ' . $request->status . 
                      ($request->observation ? ' - Observación: ' . $request->observation : '');
            
            $useCase($commission->id, $adminStatus, $details, false);

            // Recargar la comisión para obtener el estado actualizado
            $commission->refresh();

            // Si se marca como entregado y se proporcionan datos de firma, guardar la firma
            if (($request->status === 'Entregado' || $newStatusValue === CommissionStatus::ENTREGADO->value) 
                && ($request->has('receiver_name') || $request->has('signature_image'))) {
                // Crear la firma de entrega solo si se proporcionan datos
                $signatureData = [
                    'commission_id' => $commission->id,
                    'cadete_id' => $user->id,
                    'receiver_name' => $request->receiver_name ?? '',
                    'receiver_phone' => $request->receiver_phone ?? '',
                    'notes' => $request->notes,
                    'delivery_timestamp' => $request->delivery_timestamp ?? now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ];
                
                // Solo incluir signature_image si está presente y no es null
                if ($request->has('signature_image') && !empty($request->signature_image)) {
                    $signatureData['signature_image'] = $request->signature_image;
                }
                
                DeliverySignature::create($signatureData);

                \Log::info('Firma de entrega registrada exitosamente', [
                    'cadete_id' => $user->id,
                    'commission_id' => $commission->id,
                    'receiver_name' => $request->receiver_name,
                    'receiver_phone' => $request->receiver_phone,
                    'has_signature' => !empty($request->signature_image)
                ]);
            }

            // Si el nuevo estado es ENTREGADO, ejecutar el flujo de PENDIENTE_PAGO -> PAGO_VALIDACION
            if ($adminStatus === CommissionStatus::ENTREGADO) {
                // Obtener repositorios
                $commissionRepository = app(CommissionsRepository::class);
                $currentAccountRepository = app(CurrentAccountRepository::class);

                // Cambiar estado a PENDIENTE_PAGO primero
                $commissionRepository->updateStatus($commission->id, CommissionStatus::PENDIENTE_PAGO);
                
                // Crear log de cambio a PENDIENTE_PAGO
                $logDto = new CreateCommissionLogDTO(
                    commissionId: $commission->id,
                    userId: Auth::id(),
                    previousStatus: CommissionStatus::ENTREGADO->value,
                    newStatus: CommissionStatus::PENDIENTE_PAGO->value,
                    details: 'Comisión actualizada'
                );
                $commissionRepository->createLog($logDto);

                // Si es ORDINARIA: automáticamente cambiar a PAGO_VALIDACION y crear movimiento
                if ($commissionType === CommissionType::ORDINARIA) {
                    // Actualizar estado a PAGO_VALIDACION
                    $commissionRepository->updateStatus($commission->id, CommissionStatus::PAGO_VALIDACION);
                    
                    // Crear log del cambio automático a PAGO_VALIDACION
                    $logDtoAuto = new CreateCommissionLogDTO(
                        commissionId: $commission->id,
                        userId: Auth::id(),
                        previousStatus: CommissionStatus::PENDIENTE_PAGO->value,
                        newStatus: CommissionStatus::PAGO_VALIDACION->value,
                        details: 'Cambio automático a PAGO_VALIDACION (comisión ordinaria)'
                    );
                    $commissionRepository->createLog($logDtoAuto);

                    // Obtener la comisión actualizada para crear el movimiento
                    $updatedCommission = $commissionRepository->findById($commission->id);
                    
                    // Crear movimiento en cuenta corriente
                    $this->createCurrentAccountTransaction($updatedCommission, $currentAccountRepository);
                }
                // Si es EXTRAORDINARIA: solo queda en PENDIENTE_PAGO (sin crear movimiento)
                
                // Refrescar la comisión para obtener el estado final actualizado
                $commission->refresh();
            }

            // Log de confirmación de actualización
            \Log::info('Estado de comisión actualizado exitosamente', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'status_updated' => $newStatusValue,
                'updated_at' => $commission->updated_at->toISOString(),
                'total_changes' => $commission->getChanges()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estado del envío actualizado correctamente',
                'shipment' => [
                    'id' => $commission->id,
                    'status' => $commission->status->value,
                    'status_label' => $commission->status->getCadeteStatus(),
                    'updated_at' => $commission->updated_at->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            \Log::error('Error en conversión de estado del cadete', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'status_requested' => $request->status,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error en el estado: ' . $e->getMessage(),
                'valid_statuses' => CommissionStatus::getValidCadeteStatuses()
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Error inesperado al actualizar estado de comisión', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'status_requested' => $request->status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /cadete/deliveries/:id/location - Enviar ubicación GPS
     */
    public function sendLocation(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'address' => 'nullable|string|max:255',
                'observation' => 'nullable|string|max:1000',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Cadete intentó enviar ubicación con datos inválidos', [
                'cadete_id' => Auth::id(),
                'commission_id' => $id,
                'latitude' => $request->latitude ?? 'null',
                'longitude' => $request->longitude ?? 'null',
                'errors' => $e->errors(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Datos de ubicación inválidos',
                'errors' => $e->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Verificar que el cadete tenga acceso a esta comisión
        $commission = Commission::where('cadete_id', $user->id)
                               ->where('id', $id)
                               ->first();

        if (!$commission) {
            \Log::warning('Cadete intentó enviar ubicación para comisión no autorizada', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Envío no encontrado o no tienes permisos para enviar ubicación'
            ], 404);
        }

        try {
            // Log del envío de ubicación
            \Log::info('Cadete enviando ubicación GPS', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'commission_status' => $commission->status->value,
                'location_data' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'address' => $request->address,
                    'observation' => $request->observation
                ],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            $location = ShipmentLocation::create([
                'commission_id' => $commission->id,
                'cadete_id' => $user->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'address' => $request->address,
                'observation' => $request->observation,
                'recorded_at' => now(),
            ]);

            // Log de confirmación de ubicación registrada
            \Log::info('Ubicación GPS registrada exitosamente', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'location_id' => $location->id,
                'coordinates' => [
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude
                ],
                'recorded_at' => $location->recorded_at->toISOString(),
                'total_locations_for_commission' => ShipmentLocation::where('commission_id', $commission->id)->count()
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
            \Log::error('Error al registrar ubicación GPS', [
                'cadete_id' => $user->id,
                'cadete_name' => $user->name,
                'commission_id' => $commission->id,
                'location_data' => [
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'address' => $request->address,
                    'observation' => $request->observation
                ],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
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
                                  ->pluck('count', 'status.value')
                                  ->toArray();

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
                    'pendiente' => $statsByStatus[CommissionStatus::SOLICITUD_RECIBIDA->value] ?? 0,
                    'en_transito' => $statsByStatus[CommissionStatus::EN_TRANSITO_DESTINO->value] ?? 0,
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
     * GET /cadete/deliveries - Obtener entregas del cadete con filtros avanzados
     */
    public function deliveries(Request $request): JsonResponse
    {
        return $this->getDeliveries($request, true);
    }

    /**
     * Función privada común para obtener entregas con o sin filtro de estados en proceso
     * 
     * @param Request $request
     * @param bool $filterInProcessStatuses Si es true, aplica filtro de estados en proceso cuando no se especifica status
     * @return JsonResponse
     */
    private function getDeliveries(Request $request, bool $filterInProcessStatuses): JsonResponse
    {
        $user = Auth::user();
        
        // Construir query base usando cadete_id directamente
        $query = Commission::with([
            'client:id,name,last_name,phone,address',
            'originLocation:id,name,address,origin,phone,schedule,latitude,longitude',
            'destinationLocation:id,name,address,origin,phone,schedule,latitude,longitude',
            'items:id,commission_id,type,size,quantity,detail',
            'transport:id,plate,description',
            'deliverySignature:id,commission_id,receiver_name,receiver_phone,notes,delivery_timestamp'
        ])
        ->where('cadete_id', $user->id);

        // Aplicar filtros
        // Si se envía el parámetro status, no aplicar el filtro de estados en proceso
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        } elseif ($filterInProcessStatuses) {
            // Solo mostrar comisiones en estados en proceso si no se filtra por estado específico
            $inProcessStatuses = [
                CommissionStatus::SOLICITUD_RECIBIDA->value,
                CommissionStatus::BUSCANDO_CADETE->value,
                CommissionStatus::CADETE_ASIGNADO->value,
                CommissionStatus::CADETE_EN_CAMINO_ORIGEN->value,
                CommissionStatus::EN_PUNTO_RETIRO->value,
                CommissionStatus::ENCOMIENDA_RETIRADA->value,
                CommissionStatus::EN_CAMINO_PLANTA->value,
                CommissionStatus::EN_TRANSITO_DESTINO->value,
                CommissionStatus::EN_SUCURSAL_DESTINO->value,
                CommissionStatus::EN_PROCESO_ENTREGA->value,
                CommissionStatus::EN_PLANTA->value,
            ];
            $query->whereIn('status', $inProcessStatuses);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        
        // Búsqueda por texto
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                // Búsqueda por ID de comisión directamente
                $q->where('id', 'like', "%{$search}%")
                  // Búsqueda en cliente
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('last_name', 'like', "%{$search}%")
                                 ->orWhere('address', 'like', "%{$search}%")
                                 ->orWhere('id', 'like', "%{$search}%");
                  })
                  // Búsqueda en ubicación de origen
                  ->orWhereHas('originLocation', function ($originQuery) use ($search) {
                      $originQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('address', 'like', "%{$search}%")
                                 ->orWhere('id', 'like', "%{$search}%");
                  })
                  // Búsqueda en ubicación de destino
                  ->orWhereHas('destinationLocation', function ($destQuery) use ($search) {
                      $destQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('address', 'like', "%{$search}%")
                               ->orWhere('id', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'estimated_pickup_time':
                $query->orderBy('date', $sortOrder);
                break;
            case 'commission_amount':
                $query->orderBy('total', $sortOrder);
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        // Paginación
        $perPage = $request->get('per_page', 20);
        $deliveries = $query->paginate($perPage);

        // Transformar datos
        $transformedDeliveries = $deliveries->getCollection()->map(function ($commission) {
            // Obtener coordenadas de ubicaciones
            $pickupCoordinates = $this->getLocationCoordinates($commission->originLocation);
            $deliveryCoordinates = $this->getLocationCoordinates($commission->destinationLocation);
            
            return [
                'id' => $commission->id,
                'tracking_number' => $commission->id,
                'customer_name' => $commission->client ? 
                    $commission->client->name . ' ' . $commission->client->last_name : 'N/A',
                'customer_address' => $commission->client ? $commission->client->address . ' ,' . $commission->client->origin : 'N/A',
                'customer_phone' => $commission->client ? $commission->client->phone : 'N/A',
                'pickup_address' => $commission->originLocation ? 
                    $commission->originLocation->name . ', ' . $commission->originLocation->address  . ' ,' . $commission->originLocation->origin : 'N/A',
                'pickup_phone' => $commission->originLocation ? $commission->originLocation->phone  : 'N/A',
                'pickup_latitude' => $pickupCoordinates ? ($pickupCoordinates['latitude'] ?? null) : null,
                'pickup_longitude' => $pickupCoordinates ? ($pickupCoordinates['longitude'] ?? null) : null,
                'delivery_address' => $commission->destinationLocation ? 
                    $commission->destinationLocation->name . ', ' . $commission->destinationLocation->address  . ' ,' . $commission->destinationLocation->origin : 'N/A',
                'delivery_phone' => $commission->destinationLocation ? $commission->destinationLocation->phone  : 'N/A',
                'delivery_latitude' => $deliveryCoordinates ? ($deliveryCoordinates['latitude'] ?? null) : null,
                'delivery_longitude' => $deliveryCoordinates ? ($deliveryCoordinates['longitude'] ?? null) : null,
                'status' => $commission->status->getCadeteStatus(),
                'status_label' => $commission->status->getCadeteStatus(),
                'estimated_pickup_time' => $commission->originLocation ? $commission->originLocation->schedule : 'N/A',
                'estimated_delivery_time' => $commission->destinationLocation ? $commission->destinationLocation->schedule : 'N/A', 
                'commission_amount' => $commission->total,
                'created_at' => $commission->created_at->toISOString(),
                'updated_at' => $commission->updated_at->toISOString(),
                'notes' => $commission->notes ?? 'Sin notas adicionales',
                'items_count' => $commission->items->sum('quantity') ?? 0,
                'weight_kg' => $commission->items->sum('weight') ?? 0,
                'dimensions' => $this->calculateDimensions($commission->items),
                'signature_data' => $commission->deliverySignature ? [
                    'receiver_name' => $commission->deliverySignature->receiver_name,
                    'receiver_phone' => $commission->deliverySignature->receiver_phone,
                    'notes' => $commission->deliverySignature->notes,
                    'signature_image' => \DB::table('delivery_signatures')->where('commission_id', $commission->id)->value('signature_image'),
                    'delivery_timestamp' => $commission->deliverySignature->delivery_timestamp->toISOString(),
                ] : null,
            ];
        });

        // Calcular resumen con TODAS las comisiones del cadete (sin filtro de estados en proceso)
        $summaryQuery = Commission::where('cadete_id', $user->id);
        
        // Aplicar filtros de fecha si existen (pero no el filtro de estados)
        if ($request->filled('date_from')) {
            $summaryQuery->whereDate('date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $summaryQuery->whereDate('date', '<=', $request->date_to);
        }
        
        $summary = $this->calculateDeliveriesSummaryWithFilters($summaryQuery);

        return response()->json([
            'success' => true,
            'message' => 'Entregas obtenidas correctamente',
            'data' => [
                'deliveries' => $transformedDeliveries,
                'pagination' => [
                    'current_page' => $deliveries->currentPage(),
                    'per_page' => $deliveries->perPage(),
                    'total' => $deliveries->total(),
                    'last_page' => $deliveries->lastPage(),
                    'from' => $deliveries->firstItem(),
                    'to' => $deliveries->lastItem()
                ],
                'summary' => $summary
            ]
        ]);
    }

    /**
     * GET /cadete/shipments - Obtener envíos del cadete (alias de deliveries con estructura diferente)
     */
    public function shipments(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Verificar si el cadete tiene transportes asignados
        $transportIds = Transport::where('cadete_id', $user->id)->pluck('id');
        
        if ($transportIds->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No tienes transportes asignados',
                'shipments' => []
            ]);
        }
        
        // Construir query base usando cadete_id directamente
        $query = Commission::with([
            'client:id,name,last_name,phone,address',
            'originLocation:id,name,address,origin,phone,schedule',
            'destinationLocation:id,name,address,origin,phone,schedule',
            'items:id,commission_id,type,size,quantity,detail',
            'transport:id,plate,description',
            'deliverySignature:id,commission_id,receiver_name,receiver_phone,notes,delivery_timestamp'
        ])
        ->where('cadete_id', $user->id);

        // Aplicar filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }
        
        // Búsqueda por texto
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                // Búsqueda por ID de comisión directamente
                $q->where('id', 'like', "%{$search}%")
                  // Búsqueda en cliente
                  ->orWhereHas('client', function ($clientQuery) use ($search) {
                      $clientQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('last_name', 'like', "%{$search}%")
                                 ->orWhere('address', 'like', "%{$search}%")
                                 ->orWhere('id', 'like', "%{$search}%");
                  })
                  // Búsqueda en ubicación de origen
                  ->orWhereHas('originLocation', function ($originQuery) use ($search) {
                      $originQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('address', 'like', "%{$search}%")
                                 ->orWhere('id', 'like', "%{$search}%");
                  })
                  // Búsqueda en ubicación de destino
                  ->orWhereHas('destinationLocation', function ($destQuery) use ($search) {
                      $destQuery->where('name', 'like', "%{$search}%")
                               ->orWhere('address', 'like', "%{$search}%")
                               ->orWhere('id', 'like', "%{$search}%");
                  });
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        switch ($sortBy) {
            case 'date':
                $query->orderBy('date', $sortOrder);
                break;
            case 'total':
                $query->orderBy('total', $sortOrder);
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortOrder);
                break;
        }

        // Paginación
        $perPage = $request->get('per_page', 20);
        $shipments = $query->paginate($perPage);

        // Transformar datos a la estructura esperada por los tests
        $transformedShipments = $shipments->getCollection()->map(function ($commission) {
            // Obtener coordenadas de ubicaciones
            $pickupCoordinates = $this->getLocationCoordinates($commission->originLocation);
            $deliveryCoordinates = $this->getLocationCoordinates($commission->destinationLocation);
            
            return [
                'id' => $commission->id,
                'tracking_number' => $commission->id,
                'status' => $commission->status->value,
                'status_label' => $commission->status->getCadeteStatus(),
                'date' => $commission->date ? $commission->date->format('Y-m-d') : null,
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
                    'phone' => $commission->originLocation->phone,
                    'latitude' => $pickupCoordinates ? ($pickupCoordinates['latitude'] ?? null) : null,
                    'longitude' => $pickupCoordinates ? ($pickupCoordinates['longitude'] ?? null) : null,
                ] : null,
                'destination' => $commission->destinationLocation ? [
                    'id' => $commission->destinationLocation->id,
                    'name' => $commission->destinationLocation->name,
                    'address' => $commission->destinationLocation->address,
                    'phone' => $commission->destinationLocation->phone,
                    'latitude' => $deliveryCoordinates ? ($deliveryCoordinates['latitude'] ?? null) : null,
                    'longitude' => $deliveryCoordinates ? ($deliveryCoordinates['longitude'] ?? null) : null,
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

        // Obtener filtros aplicados
        $filters = [
            'status' => $request->get('status'),
            'date' => $request->get('date'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'search' => $request->get('search'),
        ];

        return response()->json([
            'success' => true,
            'shipments' => $transformedShipments,
            'total' => $shipments->total(),
            'filters' => $filters
        ]);
    }

    /**
     * GET /cadete/earnings - Obtener ganancias detalladas del cadete
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Validar parámetros
        $request->validate([
            'period' => 'nullable|string|in:today,week,month,year,custom',
            'date_from' => 'nullable|date|required_if:period,custom',
            'date_to' => 'nullable|date|required_if:period,custom|after_or_equal:date_from',
            'status' => 'nullable|string|in:all,paid,pending,cancelled',
            'payment_method' => 'nullable|string|in:all,cash,card,transfer',
            'delivery_type' => 'nullable|string|in:all,standard,express,urgent',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Determinar fechas del período
        $periodDates = $this->getPeriodDates($request);
        $startDate = $periodDates['start'];
        $endDate = $periodDates['end'];
        $periodLabel = $periodDates['label'];

        // Construir query base para comisiones del cadete
        $baseQuery = Commission::where('cadete_id', $user->id)
                              ->whereBetween('date', [$startDate, $endDate]);

        // Aplicar filtros adicionales
        if ($request->filled('status') && $request->status !== 'all') {
            $baseQuery = $this->applyStatusFilter($baseQuery, $request->status);
        }

        // Obtener comisiones para cálculos
        $commissions = $baseQuery->with([
            'client', 
            'destination', 
            'originLocation', 
            'destinationLocation'
        ])->get();

        // Obtener el porcentaje de comisión del cadete
        $cadeteCommissionPercentage = $user->commission_percentage ?? 0;

        // Calcular resumen general
        $summary = $this->calculateEarningsSummary($commissions, $periodLabel, $cadeteCommissionPercentage);

        // Calcular breakdown por método de pago
        $breakdown = $this->calculatePaymentBreakdown($commissions, $cadeteCommissionPercentage);

        // Calcular estadísticas por estado de pago
        $paymentStatus = $this->calculatePaymentStatus($commissions, $cadeteCommissionPercentage);

        // Calcular breakdown diario
        $dailyBreakdown = $this->calculateDailyBreakdown($commissions, $startDate, $endDate, $cadeteCommissionPercentage);

        // Calcular rutas más rentables
        $topRoutes = $this->calculateTopRoutes($commissions, $cadeteCommissionPercentage);

        // Calcular métricas de performance
        $performanceMetrics = $this->calculatePerformanceMetrics($commissions, $user->id);

        // Obtener pagos recientes con paginación
        $recentPayments = $this->getRecentPayments($commissions, $request, $cadeteCommissionPercentage);

        // Calcular crecimiento vs período anterior
        $growthData = $this->calculateGrowthComparison($user->id, $startDate, $endDate, $cadeteCommissionPercentage);

        return response()->json([
            'success' => true,
            'message' => 'Ganancias obtenidas correctamente',
            'data' => [
                'summary' => $summary,
                'breakdown' => $breakdown,
                'payment_status' => $paymentStatus,
                'daily_breakdown' => $dailyBreakdown,
                'top_routes' => $topRoutes,
                'performance_metrics' => array_merge($performanceMetrics, $growthData),
                'recent_payments' => $recentPayments['data'],
                'pagination' => $recentPayments['pagination'],
                'commission_info' => [
                    'cadete_commission_percentage' => $cadeteCommissionPercentage,
                    'explanation' => "El cadete recibe el {$cadeteCommissionPercentage}% del total de cada comisión entregada"
                ]
            ]
        ]);
    }

    /**
     * Calcular resumen de entregas
     */
    private function calculateDeliveriesSummary(): array
    {
        // Obtener el ID del cadete autenticado
        $user = Auth::user();
        
        $totalDeliveries = Commission::where('cadete_id', $user->id)->count();
        
        // Estados pendientes (solo comisiones recién asignadas)
        $pending = Commission::where('cadete_id', $user->id)
                            ->whereIn('status', [
                                CommissionStatus::CADETE_ASIGNADO,
                                // Estados administrativos que se mapean a "En proceso" para el cadete
                                CommissionStatus::SOLICITUD_RECIBIDA,
                                CommissionStatus::BUSCANDO_CADETE,
                                CommissionStatus::PENDIENTE_PAGO,
                                CommissionStatus::PAGO_VALIDACION,
                                CommissionStatus::PAGO_CONFIRMADO,
                                CommissionStatus::EN_SUCURSAL_DESTINO,
                                CommissionStatus::CANCELADO,
                                CommissionStatus::EN_ANALISIS
                            ])
                            ->count();
        
        // Estados en progreso (desde en camino al origen hasta en proceso de entrega)
        $inProgress = Commission::where('cadete_id', $user->id)
                               ->whereIn('status', [
                                   CommissionStatus::CADETE_EN_CAMINO_ORIGEN,
                                   CommissionStatus::EN_PUNTO_RETIRO,
                                   CommissionStatus::ENCOMIENDA_RETIRADA,
                                   CommissionStatus::EN_CAMINO_PLANTA,
                                   CommissionStatus::EN_TRANSITO_DESTINO,
                                   CommissionStatus::EN_PROCESO_ENTREGA
                               ])
                               ->count();
        
        // Estados completados exitosamente
        $completed = Commission::where('cadete_id', $user->id)
                              ->whereIn('status', [
                                  CommissionStatus::ENTREGADO,
                                  CommissionStatus::RETIRADO_SUCURSAL
                              ])
                              ->count();
        
        // Estados de incidencia que puede reportar el cadete (no incluye CANCELADO)
        $cancelled = Commission::where('cadete_id', $user->id)
                              ->whereIn('status', [
                                  CommissionStatus::INTENTO_ENTREGA_FALLIDO,
                                  CommissionStatus::REPROGRAMANDO_ENTREGA,
                                  CommissionStatus::DISPONIBLE_RETIRO,
                                  CommissionStatus::EN_DEVOLUCION,
                                  CommissionStatus::DEVUELTO_REMITENTE
                              ])
                              ->count();
        
        // Calcular ganancias totales de comisiones completadas
        $totalCommissionAmount = Commission::where('cadete_id', $user->id)
                                          ->whereIn('status', [
                                              CommissionStatus::ENTREGADO,
                                              CommissionStatus::RETIRADO_SUCURSAL
                                          ])
                                          ->sum('total');
        
        // Obtener el porcentaje de comisión del cadete
        $cadeteCommissionPercentage = $user->commission_percentage ?? 0;
        
        // Calcular ganancias reales del cadete basadas en su porcentaje
        $totalEarnings = $totalCommissionAmount * ($cadeteCommissionPercentage / 100);

        return [
            'total_deliveries' => $totalDeliveries,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'total_earnings' => round($totalEarnings, 2),
            'total_commission_amount' => round($totalCommissionAmount, 2),
            'commission_percentage' => $cadeteCommissionPercentage
        ];
    }

    /**
     * Calcular resumen de entregas basado en una consulta de comisiones
     */
    private function calculateDeliveriesSummaryWithFilters($baseQuery)
    {
        $user = Auth::user();
        $cadeteId = $user->id;

        // Clonar la query base para cada cálculo para evitar interferencias
        $totalQuery = clone $baseQuery;
        $pendingQuery = clone $baseQuery;
        $inProgressQuery = clone $baseQuery;
        $completedQuery = clone $baseQuery;
        $cancelledQuery = clone $baseQuery;
        $earningsQuery = clone $baseQuery;

        // Total de comisiones que coinciden con los filtros
        $totalDeliveries = $totalQuery->where('cadete_id', $cadeteId)->count();

        // Estados pendientes (solo comisiones recién asignadas)
        // NOTA: PENDIENTE_PAGO, PAGO_VALIDACION y PAGO_CONFIRMADO se cuentan como completados
        $pending = $pendingQuery->where('cadete_id', $cadeteId)
                               ->whereIn('status', [
                                   CommissionStatus::CADETE_ASIGNADO,
                                   // Estados administrativos que se mapean a "En proceso" para el cadete
                                   CommissionStatus::SOLICITUD_RECIBIDA,
                                   CommissionStatus::BUSCANDO_CADETE,
                                   CommissionStatus::EN_SUCURSAL_DESTINO,
                                   CommissionStatus::CANCELADO,
                                   CommissionStatus::EN_ANALISIS
                               ])
                               ->count();

        // Estados en progreso (desde en camino al origen hasta en proceso de entrega)
        $inProgress = $inProgressQuery->where('cadete_id', $cadeteId)
                                     ->whereIn('status', [
                                         CommissionStatus::CADETE_EN_CAMINO_ORIGEN,
                                         CommissionStatus::EN_PUNTO_RETIRO,
                                         CommissionStatus::ENCOMIENDA_RETIRADA,
                                         CommissionStatus::EN_CAMINO_PLANTA,
                                         CommissionStatus::EN_TRANSITO_DESTINO,
                                         CommissionStatus::EN_PROCESO_ENTREGA
                                     ])
                                     ->count();

        // Estados completados exitosamente
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $completed = $completedQuery->where('cadete_id', $cadeteId)
                                   ->whereIn('status', [
                                       CommissionStatus::ENTREGADO,
                                       CommissionStatus::RETIRADO_SUCURSAL,
                                       CommissionStatus::PENDIENTE_PAGO,
                                       CommissionStatus::PAGO_VALIDACION,
                                       CommissionStatus::PAGO_CONFIRMADO
                                   ])
                                   ->count();

        // Estados de incidencia que puede reportar el cadete
        $cancelled = $cancelledQuery->where('cadete_id', $cadeteId)
                                   ->whereIn('status', [
                                       CommissionStatus::INTENTO_ENTREGA_FALLIDO,
                                       CommissionStatus::REPROGRAMANDO_ENTREGA,
                                       CommissionStatus::DISPONIBLE_RETIRO,
                                       CommissionStatus::EN_DEVOLUCION,
                                       CommissionStatus::DEVUELTO_REMITENTE
                                   ])
                                   ->count();

        // Calcular ganancias totales de comisiones completadas
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $totalCommissionAmount = $earningsQuery->where('cadete_id', $cadeteId)
                                              ->whereIn('status', [
                                                  CommissionStatus::ENTREGADO,
                                                  CommissionStatus::RETIRADO_SUCURSAL,
                                                  CommissionStatus::PENDIENTE_PAGO,
                                                  CommissionStatus::PAGO_VALIDACION,
                                                  CommissionStatus::PAGO_CONFIRMADO
                                              ])
                                              ->sum('total');
        
        // Obtener el porcentaje de comisión del cadete
        $cadeteCommissionPercentage = $user->commission_percentage ?? 0;
        
        // Calcular ganancias reales del cadete basadas en su porcentaje
        $totalEarnings = $totalCommissionAmount * ($cadeteCommissionPercentage / 100);

        return [
            'total_deliveries' => $totalDeliveries,
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'total_earnings' => round($totalEarnings, 2),
            'total_commission_amount' => round($totalCommissionAmount, 2),
            'commission_percentage' => $cadeteCommissionPercentage
        ];
    }

    /**
     * Calcular dimensiones totales de los items
     */
    private function calculateDimensions($items): string
    {
        if ($items->isEmpty()) {
            return 'N/A';
        }

        // Contar items por tamaño usando los valores del enum
        $smallCount = $items->where('size', CommissionItemSize::SMALL)->sum('quantity');
        $largeCount = $items->where('size', CommissionItemSize::LARGE)->sum('quantity');
        
        if ($smallCount > 0 && $largeCount > 0) {
            return "{$smallCount} chicos, {$largeCount} grandes";
        } elseif ($smallCount > 0) {
            return "{$smallCount} chicos";
        } elseif ($largeCount > 0) {
            return "{$largeCount} grandes";
        }

        return 'Tamaño no especificado';
    }

    /**
     * Obtener etiqueta legible del estado para cadete
     */
    private function getStatusLabel(CommissionStatus $status): string
    {
        return $status->getCadeteStatus();
    }

    /**
     * Obtener etiqueta legible del rol para cadete
     */
    private function getRoleLabel(string $role): string
    {
        return match ($role) {
            'cadete' => 'Cadete',
            'admin' => 'Administrador',
            'branch_manager' => 'Gerente de Sucursal',
            'super_admin' => 'Super Administrador',
            default => 'Desconocido',
        };
    }

    /**
     * Obtener etiqueta legible del tipo de contratación para cadete
     */
    private function getContractTypeLabel(string $type): string
    {
        return match ($type) {
            'fixed_salary' => 'Salario Fijo',
            'commission_based' => 'Por Comisión',
            'mixed' => 'Mixto',
            default => 'Desconocido',
        };
    }

    /**
     * Determinar fechas del período solicitado
     */
    private function getPeriodDates(Request $request): array
    {
        $period = $request->get('period', 'month');
        
        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                $label = 'Hoy';
                break;
                
            case 'week':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                $label = 'Esta semana';
                break;
                
            case 'month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $label = 'Este mes';
                break;
                
            case 'year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                $label = 'Este año';
                break;
                
            case 'custom':
                $startDate = \Carbon\Carbon::parse($request->date_from)->startOfDay();
                $endDate = \Carbon\Carbon::parse($request->date_to)->endOfDay();
                $label = 'Período personalizado';
                break;
                
            default:
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                $label = 'Este mes';
        }
        
        return [
            'start' => $startDate,
            'end' => $endDate,
            'label' => $label
        ];
    }

    /**
     * Aplicar filtro por estado
     */
    private function applyStatusFilter($query, string $status)
    {
        switch ($status) {
            case 'paid':
                // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
                return $query->whereIn('status', [
                    CommissionStatus::ENTREGADO,
                    CommissionStatus::RETIRADO_SUCURSAL,
                    CommissionStatus::PENDIENTE_PAGO,
                    CommissionStatus::PAGO_VALIDACION,
                    CommissionStatus::PAGO_CONFIRMADO
                ]);
            case 'pending':
                return $query->whereIn('status', [
                    CommissionStatus::EN_TRANSITO_DESTINO,
                    CommissionStatus::EN_PROCESO_ENTREGA
                ]);
            case 'cancelled':
                return $query->whereIn('status', [
                    CommissionStatus::CANCELADO,
                    CommissionStatus::INTENTO_ENTREGA_FALLIDO
                ]);
            default:
                return $query;
        }
    }

    /**
     * Calcular resumen general de ganancias
     */
    private function calculateEarningsSummary($commissions, string $periodLabel, float $commissionPercentage): array
    {
        // Usar filter() para comparar correctamente los enums
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $deliveredCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        });
        
        // Usar reduce() para sumar correctamente los totales
        $totalCommissionAmount = $deliveredCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        
        // Calcular ganancias reales del cadete basadas en su porcentaje
        $totalEarnings = $totalCommissionAmount * ($commissionPercentage / 100);
        
        $totalDeliveries = $deliveredCommissions->count();
        
        $averagePerDelivery = $totalDeliveries > 0 ? $totalEarnings / $totalDeliveries : 0;
        
        return [
            'total_earnings' => round($totalEarnings, 2),
            'total_deliveries' => $totalDeliveries,
            'commission_percentage' => $commissionPercentage,
            'total_commission_amount' => round($totalCommissionAmount, 2),
            'currency' => 'ARS',
            'period_label' => $periodLabel,
            'formatted_total' => '$' . number_format($totalEarnings, 0, ',', '.'),
            'formatted_commission_amount' => '$' . number_format($totalCommissionAmount, 0, ',', '.')
        ];
    }

    /**
     * Calcular breakdown por método de pago
     */
    private function calculatePaymentBreakdown($commissions, float $commissionPercentage): array
    {
        // Por ahora asumimos que todas las comisiones son en efectivo
        // TODO: Implementar lógica real de método de pago cuando esté disponible
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $deliveredCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        });
        
        $totalCommissionAmount = $deliveredCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        
        // Calcular ganancias reales del cadete basadas en su porcentaje
        $cashEarnings = $totalCommissionAmount * ($commissionPercentage / 100);
        
        $cardEarnings = 0; // TODO: Implementar cuando esté disponible
        $transferEarnings = 0; // TODO: Implementar cuando esté disponible
        $bonuses = 0; // TODO: Implementar cuando esté disponible
        $deductions = 0; // TODO: Implementar cuando esté disponible
        
        $netEarnings = $cashEarnings + $cardEarnings + $transferEarnings + $bonuses - $deductions;
        
        return [
            'cash_earnings' => round($cashEarnings, 2),
            'card_earnings' => round($cardEarnings, 2),
            'transfer_earnings' => round($transferEarnings, 2),
            'bonuses' => round($bonuses, 2),
            'deductions' => round($deductions, 2),
            'net_earnings' => round($netEarnings, 2),
            'total_commission_amount' => round($totalCommissionAmount, 2),
            'commission_percentage' => $commissionPercentage
        ];
    }

    /**
     * Calcular estadísticas por estado de pago
     */
    private function calculatePaymentStatus($commissions, float $commissionPercentage): array
    {
        $totalCommissions = $commissions->count();
        $totalAmount = $commissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $paidCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        });
        $paidCommissionAmount = $paidCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $paidEarnings = $paidCommissionAmount * ($commissionPercentage / 100);
        $paidCount = $paidCommissions->count();
        
        $pendingCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::EN_TRANSITO_DESTINO
                || $commission->status === CommissionStatus::EN_PROCESO_ENTREGA;
        });
        $pendingCommissionAmount = $pendingCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $pendingEarnings = $pendingCommissionAmount * ($commissionPercentage / 100);
        $pendingCount = $pendingCommissions->count();
        
        $cancelledCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::CANCELADO
                || $commission->status === CommissionStatus::INTENTO_ENTREGA_FALLIDO;
        });
        $cancelledCommissionAmount = $cancelledCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $cancelledEarnings = $cancelledCommissionAmount * ($commissionPercentage / 100);
        $cancelledCount = $cancelledCommissions->count();
        
        return [
            'paid' => [
                'commission_amount' => round($paidCommissionAmount, 2),
                'earnings' => round($paidEarnings, 2),
                'count' => $paidCount,
                'percentage' => $totalAmount > 0 ? round(($paidCommissionAmount / $totalAmount) * 100, 1) : 0
            ],
            'pending' => [
                'commission_amount' => round($pendingCommissionAmount, 2),
                'earnings' => round($pendingEarnings, 2),
                'count' => $pendingCount,
                'percentage' => $totalAmount > 0 ? round(($pendingCommissionAmount / $totalAmount) * 100, 1) : 0
            ],
            'cancelled' => [
                'commission_amount' => round($cancelledCommissionAmount, 2),
                'earnings' => round($cancelledEarnings, 2),
                'count' => $cancelledCount,
                'percentage' => $totalAmount > 0 ? round(($cancelledCommissionAmount / $totalAmount) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Calcular breakdown diario
     */
    private function calculateDailyBreakdown($commissions, $startDate, $endDate, float $commissionPercentage): array
    {
        $dailyData = [];
        $currentDate = $startDate->copy();
        
        while ($currentDate <= $endDate) {
            $dateKey = $currentDate->format('Y-m-d');
            
            $dayCommissions = $commissions->filter(function ($commission) use ($currentDate) {
                return $commission->date && $commission->date->format('Y-m-d') === $currentDate->format('Y-m-d');
            });
            
            // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
            $dayDeliveredCommissions = $dayCommissions->filter(function ($commission) {
                return $commission->status === CommissionStatus::ENTREGADO 
                    || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                    || $commission->status === CommissionStatus::PENDIENTE_PAGO
                    || $commission->status === CommissionStatus::PAGO_VALIDACION
                    || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
            });
            
            $dayCommissionAmount = $dayDeliveredCommissions->reduce(function ($carry, $commission) {
                return $carry + (float) $commission->total;
            }, 0);
            
            // Calcular ganancias reales del cadete basadas en su porcentaje
            $dayEarnings = $dayCommissionAmount * ($commissionPercentage / 100);
            
            $dayDeliveries = $dayDeliveredCommissions->count();
            
            // TODO: Implementar cálculo real de horas trabajadas
            $hoursWorked = $dayDeliveries > 0 ? min(8, $dayDeliveries * 0.5) : 0;
            
            $dailyData[] = [
                'date' => $dateKey,
                'commission_amount' => round($dayCommissionAmount, 2),
                'earnings' => round($dayEarnings, 2),
                'deliveries' => $dayDeliveries,
                'hours_worked' => round($hoursWorked, 1),
                'formatted_commission_amount' => '$' . number_format($dayCommissionAmount, 0, ',', '.'),
                'formatted_earnings' => '$' . number_format($dayEarnings, 0, ',', '.')
            ];
            
            $currentDate->addDay();
        }
        
        return $dailyData;
    }

    /**
     * Calcular rutas más rentables
     */
    private function calculateTopRoutes($commissions, float $commissionPercentage): array
    {
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $deliveredCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        });
        
        $routes = $deliveredCommissions->groupBy(function ($commission) {
            $origin = $commission->destination->origin ?? 'N/A';
            $destination = $commission->destination->destination ?? 'N/A';
            return $origin . ' → ' . $destination;
        })->map(function ($routeCommissions, $routeName) use ($commissionPercentage) {
            $routeCommissionAmount = $routeCommissions->reduce(function ($carry, $commission) {
                return $carry + (float) $commission->total;
            }, 0);
            // Calcular ganancias reales del cadete basadas en su porcentaje
            $earnings = $routeCommissionAmount * ($commissionPercentage / 100);
            $deliveries = $routeCommissions->count();
            $averagePerDelivery = $deliveries > 0 ? $earnings / $deliveries : 0;
            
            return [
                'route' => $routeName,
                'commission_amount' => round($routeCommissionAmount, 2),
                'earnings' => round($earnings, 2),
                'deliveries' => $deliveries,
                'average_per_delivery' => round($averagePerDelivery, 2),
                'percentage' => 0 // TODO: Calcular porcentaje del total
            ];
        })->sortByDesc('earnings')->take(5)->values();
        
        // Calcular porcentajes
        $totalEarnings = $routes->sum('earnings');
        if ($totalEarnings > 0) {
            $routes = $routes->map(function ($route) use ($totalEarnings) {
                $route['percentage'] = round(($route['earnings'] / $totalEarnings) * 100, 1);
                return $route;
            });
        }
        
        return $routes->toArray();
    }

    /**
     * Calcular métricas de performance
     */
    private function calculatePerformanceMetrics($commissions, int $cadeteId): array
    {
        $totalDeliveries = $commissions->count();
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $completedDeliveries = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        })->count();
        
        $deliverySuccessRate = $totalDeliveries > 0 ? round(($completedDeliveries / $totalDeliveries) * 100, 1) : 0;
        
        // TODO: Implementar cálculos reales cuando estén disponibles
        $customerRating = 4.8; // Placeholder
        $onTimePercentage = 87.5; // Placeholder
        
        return [
            'delivery_success_rate' => $deliverySuccessRate,
            'customer_rating' => $customerRating,
            'on_time_percentage' => $onTimePercentage
        ];
    }

    /**
     * Obtener pagos recientes con paginación
     */
    private function getRecentPayments($commissions, Request $request, float $commissionPercentage): array
    {
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        
        // Filtrar solo comisiones completadas
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $completedCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        });
        
        // Simular pagos basados en comisiones (TODO: Implementar tabla real de pagos)
        $payments = $completedCommissions->map(function ($commission, $index) use ($commissionPercentage) {
            $commissionAmount = $commission->total;
            // Calcular ganancias reales del cadete basadas en su porcentaje
            $earnings = $commissionAmount * ($commissionPercentage / 100);
            
            return [
                'id' => $commission->id,
                'date' => $commission->date ? $commission->date->toISOString() : null,
                'commission_amount' => $commissionAmount,
                'earnings' => round($earnings, 2),
                'method' => 'cash', // TODO: Implementar método real de pago
                'status' => 'paid',
                'reference' => 'TXN-' . $commission->id,
                'deliveries_count' => 1,
                'formatted_commission_amount' => '$' . number_format($commissionAmount, 0, ',', '.'),
                'formatted_earnings' => '$' . number_format($earnings, 0, ',', '.')
            ];
        })->sortByDesc('date');
        
        // Paginación manual
        $total = $payments->count();
        $offset = ($page - 1) * $perPage;
        $paginatedPayments = $payments->slice($offset, $perPage);
        
        return [
            'data' => $paginatedPayments->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    /**
     * Calcular comparación de crecimiento
     */
    private function calculateGrowthComparison(int $cadeteId, $startDate, $endDate, float $commissionPercentage): array
    {
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $currentPeriodCommissionAmount = Commission::where('cadete_id', $cadeteId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', [
                CommissionStatus::ENTREGADO,
                CommissionStatus::RETIRADO_SUCURSAL,
                CommissionStatus::PENDIENTE_PAGO,
                CommissionStatus::PAGO_VALIDACION,
                CommissionStatus::PAGO_CONFIRMADO
            ])
            ->sum('total');
        
        // Calcular ganancias reales del cadete basadas en su porcentaje
        $currentPeriodEarnings = $currentPeriodCommissionAmount * ($commissionPercentage / 100);
        
        // Calcular período anterior
        $periodLength = $startDate->diffInDays($endDate);
        $previousStartDate = $startDate->copy()->subDays($periodLength);
        $previousEndDate = $startDate->copy()->subDay();
        
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $previousPeriodCommissionAmount = Commission::where('cadete_id', $cadeteId)
            ->whereBetween('date', [$previousStartDate, $previousEndDate])
            ->whereIn('status', [
                CommissionStatus::ENTREGADO,
                CommissionStatus::RETIRADO_SUCURSAL,
                CommissionStatus::PENDIENTE_PAGO,
                CommissionStatus::PAGO_VALIDACION,
                CommissionStatus::PAGO_CONFIRMADO
            ])
            ->sum('total');
        
        // Calcular ganancias reales del cadete basadas en su porcentaje
        $previousPeriodEarnings = $previousPeriodCommissionAmount * ($commissionPercentage / 100);
        
        $growthPercentage = $previousPeriodEarnings > 0 
            ? round((($currentPeriodEarnings - $previousPeriodEarnings) / $previousPeriodEarnings) * 100, 1)
            : 0;
        
        $trend = $growthPercentage >= 0 ? 'up' : 'down';
        
        return [
            'earnings_growth' => [
                'percentage' => abs($growthPercentage),
                'compared_to' => 'período_anterior',
                'trend' => $trend
            ],
            'commission_growth' => [
                'current_period_commission' => round($currentPeriodCommissionAmount, 2),
                'previous_period_commission' => round($previousPeriodCommissionAmount, 2),
                'current_period_earnings' => round($currentPeriodEarnings, 2),
                'previous_period_earnings' => round($previousPeriodEarnings, 2)
            ]
        ];
    }

    /**
     * Obtiene las coordenadas de una ubicación
     * Primero intenta desde la base de datos, si no están disponibles usa Google Maps API
     */
    private function getLocationCoordinates($location): ?array
    {
        if (!$location) {
            return null;
        }

        // Si ya tiene coordenadas en la base de datos, las usamos
        if ($location->hasCoordinates()) {
            return $location->getCoordinates();
        }

        // Si no tiene coordenadas, las calculamos con Nominatim (OpenStreetMap) - Gratuito
        try {
            $nominatimService = new \App\Services\NominatimService();
            $coordinates = $nominatimService->getCoordinates(
                $location->address,
                $location->origin
            );

            // Si obtuvimos coordenadas, las guardamos en la base de datos para futuras consultas
            if ($coordinates) {
                $location->update([
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude']
                ]);
                
                // Refrescar el modelo para que tenga las coordenadas actualizadas
                $location->refresh();
                
                \Log::info('Coordenadas calculadas y guardadas para ubicación', [
                    'location_id' => $location->id,
                    'address' => $location->address,
                    'origin' => $location->origin,
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude']
                ]);
                
                return $coordinates;
            } else {
                \Log::warning('No se pudieron calcular coordenadas para ubicación', [
                    'location_id' => $location->id,
                    'address' => $location->address,
                    'origin' => $location->origin
                ]);
                return null;
            }
        } catch (\Exception $e) {
            \Log::error('Error al calcular coordenadas para ubicación', [
                'location_id' => $location->id,
                'address' => $location->address,
                'origin' => $location->origin,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * GET /cadete/deliveries-history - Historial de entregas del cadete
     */
    public function deliveriesHistory(Request $request): JsonResponse
    {
        return $this->getDeliveries($request, false);
    }

    /**
     * Crea una transacción en cuenta corriente por el monto de la comisión como saldo deudor
     */
    private function createCurrentAccountTransaction($commission, CurrentAccountRepository $currentAccountRepository): void
    {
        // Verificar si ya existe una transacción con esta referencia para evitar duplicados
        $reference = "COM-{$commission->id}";
        if (CurrentAccount::where('reference', $reference)->exists()) {
            \Log::info('Transacción en cuenta corriente ya existe para esta comisión', [
                'commission_id' => $commission->id,
                'reference' => $reference,
            ]);
            return;
        }

        // Asegurar que la relación destination esté cargada
        if (!$commission->relationLoaded('destination')) {
            $commission->load('destination');
        }

        $origin = $commission->destination ? $commission->destination->origin : 'Origen';
        $destination = $commission->destination ? $commission->destination->destination : 'Destino';

        $currentAccountDTO = new CreateCurrentAccountDTO(
            customerId: $commission->client_id,
            type: 'debit', // Saldo negativo (deuda)
            amount: $commission->total,
            description: "Comisión #{$commission->id} - {$origin} a {$destination}",
            reference: $reference,
            transactionDate: $commission->date->format('Y-m-d'),
            paymentMethod: null,
            observations: "Comisión registrada a cuenta corriente como saldo deudor",
            userId: Auth::id() ?? 1, // Usar ID 1 como fallback si no hay usuario autenticado
        );

        $currentAccountRepository->create($currentAccountDTO);
    }
}