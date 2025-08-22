<?php

namespace App\Http\Controllers\Admin;

use App\CadetePayment;
use App\Shared\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Commission;
use App\Shared\Enums\CommissionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CadetePaymentController extends Controller
{
    /**
     * GET /api/admin/cadete-payments - Listar todos los pagos de cadetes
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'cadete_id' => 'nullable|integer|exists:users,id',
            'payment_type' => 'nullable|string|in:monthly,biweekly,weekly,bonus,advance,other',
            'status' => 'nullable|string|in:pending,paid,cancelled',
            'payment_method' => 'nullable|string|in:cash,bank_transfer,check,other',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CadetePayment::with(['cadete:id,name,email', 'admin:id,name,email']);

        // Filtros
        if ($request->filled('cadete_id')) {
            $query->forCadete($request->cadete_id);
        }

        if ($request->filled('payment_type')) {
            $query->byPaymentType($request->payment_type);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byPeriod($request->date_from, $request->date_to);
        }

        // Ordenamiento
        $query->orderBy('payment_date', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 20);
        $payments = $query->paginate($perPage);

        // Agregar labels en español a cada pago
        $paymentsData = $payments->items();
        foreach ($paymentsData as $payment) {
            $payment->payment_type_label = $payment->payment_type_label;
            $payment->payment_method_label = $payment->payment_method_label;
            $payment->status_label = $payment->status_label;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pagos obtenidos correctamente',
            'data' => $paymentsData,
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'filters' => [
                'payment_types' => CadetePayment::getPaymentTypes(),
                'payment_methods' => CadetePayment::getPaymentMethods(),
                'statuses' => CadetePayment::getStatuses(),
            ]
        ]);
    }

    /**
     * POST /api/admin/cadete-payments - Crear un nuevo pago
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cadete_id' => 'required|integer|exists:users,id',
            'payment_type' => 'required|string|in:monthly,biweekly,weekly,bonus,advance,other',
            'payment_method' => 'required|string|in:cash,bank_transfer,check,other',
            'base_salary' => 'nullable|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'deduction_amount' => 'nullable|numeric|min:0',
            'payment_date' => 'required|date',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'reference_number' => 'nullable|string|max:100',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar que el usuario sea un cadete
        $cadete = User::find($request->cadete_id);
        if (!in_array($cadete->role->value, [UserRole::CADETE->value, UserRole::CADETE_EXTERNO->value])) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario especificado no es un cadete'
            ], 400);
        }

        // Verificar que no exista un pago duplicado para el mismo período
        $existingPayment = CadetePayment::where('cadete_id', $request->cadete_id)
            ->where('payment_type', $request->payment_type)
            ->where('period_start', 'LIKE', $request->period_start . '%')
            ->where('period_end', 'LIKE', $request->period_end . '%')
            ->first();



        if ($existingPayment) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un pago para este cadete en el período especificado'
            ], 400);
        }

        // Calcular ganancias por comisiones del período
        $commissionAmount = $this->calculateCommissionAmount(
            $request->cadete_id,
            $request->period_start,
            $request->period_end
        );

        // Crear el pago
        $payment = CadetePayment::create([
            'cadete_id' => $request->cadete_id,
            'admin_id' => Auth::id(),
            'payment_type' => $request->payment_type,
            'payment_method' => $request->payment_method,
            'base_salary' => $request->base_salary ?? 0,
            'commission_amount' => $commissionAmount,
            'bonus_amount' => $request->bonus_amount ?? 0,
            'deduction_amount' => $request->deduction_amount ?? 0,
            'payment_date' => $request->payment_date,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'description' => $request->description,
            'notes' => $request->notes,
            'reference_number' => $request->reference_number,
            'transaction_id' => $request->transaction_id,
            'status' => CadetePayment::STATUS_PENDING,
        ]);

        // Cargar relaciones
        $payment->load(['cadete:id,name,email', 'admin:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Pago creado exitosamente',
            'data' => $payment
        ], 201);
    }

    /**
     * GET /api/admin/cadete-payments/{id} - Obtener un pago específico
     */
    public function show(int $id): JsonResponse
    {
        // Buscar el pago manualmente
        $payment = CadetePayment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado'
            ], 404);
        }

        $payment->load(['cadete:id,name,email', 'admin:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Pago obtenido correctamente',
            'data' => [
                'id' => $payment->id,
                'cadete_id' => $payment->cadete_id,
                'admin_id' => $payment->admin_id,
                'payment_type' => $payment->payment_type,
                'payment_method' => $payment->payment_method,
                'base_salary' => $payment->base_salary,
                'commission_amount' => $payment->commission_amount,
                'bonus_amount' => $payment->bonus_amount,
                'deduction_amount' => $payment->deduction_amount,
                'net_amount' => $payment->net_amount,
                'status' => $payment->status,
                'payment_date' => $payment->payment_date,
                'period_start' => $payment->period_start,
                'period_end' => $payment->period_end,
                'description' => $payment->description,
                'notes' => $payment->notes,
                'reference_number' => $payment->reference_number,
                'transaction_id' => $payment->transaction_id,
                'paid_at' => $payment->paid_at,
                'cadete' => $payment->cadete,
                'admin' => $payment->admin,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]
        ]);
    }

    /**
     * PUT /api/admin/cadete-payments/{id} - Actualizar un pago
     */
    public function update(Request $request, int $id): JsonResponse
    {
        // Buscar el pago manualmente
        $payment = CadetePayment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'payment_type' => 'sometimes|string|in:monthly,biweekly,weekly,bonus,advance,other',
            'payment_method' => 'sometimes|string|in:cash,bank_transfer,check,other',
            'base_salary' => 'sometimes|numeric|min:0',
            'bonus_amount' => 'sometimes|numeric|min:0',
            'deduction_amount' => 'sometimes|numeric|min:0',
            'payment_date' => 'sometimes|date',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date|after_or_equal:period_start',
            'description' => 'sometimes|nullable|string|max:500',
            'notes' => 'sometimes|nullable|string|max:1000',
            'reference_number' => 'sometimes|nullable|string|max:100',
            'transaction_id' => 'sometimes|nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar que no se esté editando un pago ya pagado
        if ($payment->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar un pago que ya ha sido pagado'
            ], 400);
        }

        // Actualizar el pago
        $payment->update($request->only([
            'payment_type', 'payment_method', 'base_salary', 'bonus_amount',
            'deduction_amount', 'payment_date', 'period_start', 'period_end',
            'description', 'notes', 'reference_number', 'transaction_id'
        ]));

        // Cargar relaciones
        $payment->load(['cadete:id,name,email', 'admin:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Pago actualizado exitosamente',
            'data' => $payment
        ]);
    }

    /**
     * DELETE /api/admin/cadete-payments/{id} - Eliminar un pago
     */
    public function destroy(int $id): JsonResponse
    {
        // Buscar el pago manualmente
        $payment = CadetePayment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado'
            ], 404);
        }

        // Verificar que no se esté eliminando un pago ya pagado
        if ($payment->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un pago que ya ha sido pagado'
            ], 400);
        }

        $payment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pago eliminado exitosamente'
        ]);
    }

    /**
     * PATCH /api/admin/cadete-payments/{id}/mark-as-paid - Marcar como pagado
     */
    public function markAsPaid(int $id): JsonResponse
    {
        // Buscar el pago manualmente
        $payment = CadetePayment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado'
            ], 404);
        }

        if ($payment->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'El pago ya está marcado como pagado'
            ], 400);
        }

        $payment->markAsPaid();

        return response()->json([
            'success' => true,
            'message' => 'Pago marcado como pagado exitosamente',
            'data' => $payment->fresh(['cadete:id,name,email', 'admin:id,name,email'])
        ]);
    }

    /**
     * PATCH /api/admin/cadete-payments/{id}/mark-as-cancelled - Marcar como cancelado
     */
    public function markAsCancelled(int $id): JsonResponse
    {
        // Buscar el pago manualmente
        $payment = CadetePayment::find($id);

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Pago no encontrado'
            ], 404);
        }

        if ($payment->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'El pago ya está cancelado'
            ], 400);
        }

        if ($payment->isPaid()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar un pago que ya ha sido pagado'
            ], 400);
        }

        $payment->markAsCancelled();

        return response()->json([
            'success' => true,
            'message' => 'Pago cancelado exitosamente',
            'data' => $payment->fresh(['cadete:id,name,email', 'admin:id,name,email'])
        ]);
    }

    /**
     * GET /api/admin/cadete-payments/cadete/{cadeteId} - Obtener pagos de un cadete específico
     */
    public function getPaymentsByCadete(Request $request, int $cadeteId): JsonResponse
    {
        $request->validate([
            'payment_type' => 'nullable|string|in:monthly,biweekly,weekly,bonus,advance,other',
            'status' => 'nullable|string|in:pending,paid,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Verificar que el usuario sea un cadete
        $cadete = User::find($cadeteId);
        if (!$cadete || !in_array($cadete->role->value, [UserRole::CADETE->value, UserRole::CADETE_EXTERNO->value])) {
            return response()->json([
                'success' => false,
                'message' => 'Cadete no encontrado'
            ], 404);
        }

        $query = CadetePayment::with(['admin:id,name,email'])
            ->where('cadete_id', $cadeteId);

        // Filtros
        if ($request->filled('payment_type')) {
            $query->byPaymentType($request->payment_type);
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byPeriod($request->date_from, $request->date_to);
        }

        // Ordenamiento
        $query->orderBy('payment_date', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 20);
        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Pagos del cadete obtenidos correctamente',
            'data' => $payments->items(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'cadete' => [
                'id' => $cadete->id,
                'name' => $cadete->name,
                'email' => $cadete->email,
                'commission_percentage' => $cadete->commission_percentage,
            ]
        ]);
    }

    /**
     * GET /api/admin/cadete-payments/summary - Resumen de pagos
     */
    public function getPaymentsSummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = CadetePayment::query();

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byPeriod($request->date_from, $request->date_to);
        }

        $summary = [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('net_amount'),
            'pending_payments' => $query->clone()->byStatus(CadetePayment::STATUS_PENDING)->count(),
            'pending_amount' => $query->clone()->byStatus(CadetePayment::STATUS_PENDING)->sum('net_amount'),
            'paid_payments' => $query->clone()->byStatus(CadetePayment::STATUS_PAID)->count(),
            'paid_amount' => $query->clone()->byStatus(CadetePayment::STATUS_PAID)->sum('net_amount'),
            'cancelled_payments' => $query->clone()->byStatus(CadetePayment::STATUS_CANCELLED)->count(),
            'cancelled_amount' => $query->clone()->byStatus(CadetePayment::STATUS_CANCELLED)->sum('net_amount'),
        ];

        // Resumen por tipo de pago
        $summaryByType = $query->clone()
            ->select('payment_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(net_amount) as total_amount'))
            ->groupBy('payment_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->payment_type => [
                    'count' => $item->count,
                    'total_amount' => $item->total_amount,
                    'label' => CadetePayment::getPaymentTypes()[$item->payment_type] ?? $item->payment_type
                ]];
            });

        $summary['by_payment_type'] = $summaryByType;

        return response()->json([
            'success' => true,
            'message' => 'Resumen de pagos obtenido correctamente',
            'data' => $summary
        ]);
    }

    /**
     * Calcular el monto de comisiones para un período específico
     */
    private function calculateCommissionAmount(int $cadeteId, string $startDate, string $endDate): float
    {
        $commissions = Commission::where('cadete_id', $cadeteId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereIn('status', [
                CommissionStatus::ENTREGADO,
                CommissionStatus::RETIRADO_SUCURSAL
            ])
            ->get();

        $totalCommissionAmount = $commissions->sum('total');
        
        // Obtener el porcentaje de comisión del cadete
        $cadete = User::find($cadeteId);
        $commissionPercentage = $cadete->commission_percentage ?? 0;
        
        return $totalCommissionAmount * ($commissionPercentage / 100);
    }

    /**
     * POST /api/admin/cadete-payments/calculate - Calcular pagos de cadete
     */
    public function calculate(Request $request): JsonResponse
    {
        \Log::info('Calculate endpoint called with data:', $request->all());
        
        try {
            $request->validate([
                'cadete_id' => 'required|integer|exists:users,id',
                'period_start' => 'required|date',
                'period_end' => 'required|date|after_or_equal:period_start',
                'base_salary' => 'nullable|numeric|min:0',
                'commission_percentage' => 'nullable|numeric|min:0|max:100',
                'income_percentage' => 'nullable|numeric|min:0|max:100',
            ]);
            
            \Log::info('Validation passed');
        } catch (\Exception $e) {
            \Log::error('Validation failed:', ['error' => $e->getMessage()]);
            throw $e;
        }

        // Verificar que el usuario sea un cadete
        $cadete = User::find($request->cadete_id);
        \Log::info('Cadete found:', ['cadete' => $cadete ? $cadete->toArray() : null]);
        
        \Log::info('Role debug:', [
            'role' => $cadete->role,
            'role_type' => gettype($cadete->role),
            'role_class' => is_object($cadete->role) ? get_class($cadete->role) : 'not_object',
            'cadete_role_value' => UserRole::CADETE->value,
            'cadete_externo_role_value' => UserRole::CADETE_EXTERNO->value,
            'comparison_result' => in_array($cadete->role, [UserRole::CADETE->value, UserRole::CADETE_EXTERNO->value])
        ]);
        
        // Verificar que el usuario sea un cadete
        // El rol puede ser un objeto enum o un string, así que comparamos ambos casos
        $isCadete = false;
        if (is_object($cadete->role) && $cadete->role instanceof UserRole) {
            // Si es un objeto enum, comparamos directamente
            $isCadete = in_array($cadete->role, [UserRole::CADETE, UserRole::CADETE_EXTERNO]);
        } else {
            // Si es un string, comparamos con los valores
            $isCadete = in_array($cadete->role, [UserRole::CADETE->value, UserRole::CADETE_EXTERNO->value]);
        }
        
        if (!$isCadete) {
            \Log::warning('User is not a cadete:', ['role' => $cadete->role, 'expected_values' => [UserRole::CADETE->value, UserRole::CADETE_EXTERNO->value]]);
            return response()->json([
                'success' => false,
                'message' => 'El usuario especificado no es un cadete'
            ], 400);
        }

        // Obtener valores del cadete o usar los del request
        $baseSalary = $request->base_salary ?? $cadete->base_salary ?? 0;
        $commissionPercentage = $request->commission_percentage ?? $cadete->commission_percentage ?? 0;
        $incomePercentage = $request->income_percentage ?? $cadete->income_percentage ?? 0;

        // Calcular días del período
        $startDate = Carbon::parse($request->period_start);
        $endDate = Carbon::parse($request->period_end);
        $daysCount = $startDate->diffInDays($endDate) + 1;

        \Log::info('Period info:', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days_count' => $daysCount
        ]);

        // Buscar comisiones del período
        $commissions = Commission::where('cadete_id', $request->cadete_id)
            ->whereDate('date', '>=', $request->period_start)
            ->whereDate('date', '<=', $request->period_end)
            ->get();

        \Log::info('Commissions found:', [
            'count' => $commissions->count(),
            'cadete_id' => $request->cadete_id,
            'period_start' => $request->period_start,
            'period_end' => $request->period_end
        ]);

        $totalCommissions = $commissions->count();
        $deliveredCommissions = $commissions->whereIn('status', [
            CommissionStatus::ENTREGADO,
            CommissionStatus::RETIRADO_SUCURSAL
        ])->count();
        $pendingCommissions = $totalCommissions - $deliveredCommissions;

        // Calcular valor total de comisiones
        $totalCommissionValue = $commissions->sum('total');
        
        // Calcular monto de comisión
        $commissionAmount = $totalCommissionValue * ($commissionPercentage / 100);

        // Calcular montos totales según el tipo de contratación
        $grossTotal = 0;
        $netAmount = 0;
        
        if ($cadete->contract_type === 'fixed_salary') {
            // Para salario fijo: base_salary + commission_amount
            $grossTotal = $baseSalary + $commissionAmount;
        } elseif ($cadete->contract_type === 'commission_based') {
            // Para comisión pura: solo commission_amount
            $grossTotal = $commissionAmount;
        } else {
            // Fallback: usar la lógica anterior
            $grossTotal = $baseSalary + $commissionAmount;
        }
        
        $netAmount = $grossTotal;

        \Log::info('Calculation completed successfully');

        // Preparar respuesta según el tipo de contratación
        $responseData = [
            'cadete_info' => [
                'id' => $cadete->id,
                'name' => $cadete->name,
                'contract_type' => $cadete->contract_type,
                'contract_type_label' => $this->getContractTypeLabel($cadete->contract_type),
            ],
            'period_info' => [
                'period_start' => $request->period_start,
                'period_end' => $request->period_end,
                'days_count' => $daysCount,
            ],
            'commissions_summary' => [
                'total_commissions' => $totalCommissions,
                'delivered_commissions' => $deliveredCommissions,
                'pending_commissions' => $pendingCommissions,
                'total_commission_value' => $totalCommissionValue,
                'commission_amount' => $commissionAmount,
            ],
            'calculated_amounts' => [
                'gross_total' => $grossTotal,
                'net_amount' => $netAmount,
            ]
        ];

        // Agregar campos según el tipo de contratación
        if ($cadete->contract_type === 'fixed_salary') {
            $responseData['cadete_info']['base_salary'] = $baseSalary;
            $responseData['calculated_amounts']['base_salary'] = $baseSalary;
            $responseData['calculated_amounts']['commission_amount'] = $commissionAmount;
        } elseif ($cadete->contract_type === 'commission_based') {
            $responseData['cadete_info']['commission_percentage'] = $commissionPercentage;
            $responseData['calculated_amounts']['commission_amount'] = $commissionAmount;
        }

        // Agregar campos opcionales si están definidos
        if ($incomePercentage > 0) {
            $responseData['cadete_info']['income_percentage'] = $incomePercentage;
            $responseData['calculated_amounts']['other_income'] = 0; // Para que puedan agregar manualmente
        }

        // Agregar campos para edición manual
        $responseData['calculated_amounts']['deductions'] = 0; // Para que puedan agregar manualmente

        return response()->json([
            'success' => true,
            'message' => 'Cálculo de pago realizado correctamente',
            'data' => $responseData
        ]);
    }

    /**
     * Helper to get contract type label.
     */
    private function getContractTypeLabel(string $contractType): string
    {
        return match ($contractType) {
            'fixed_salary' => 'Salario Fijo',
            'commission_based' => 'Comisión Pura',
            default => $contractType,
        };
    }
}
