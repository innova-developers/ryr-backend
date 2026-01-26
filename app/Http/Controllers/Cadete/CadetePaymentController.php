<?php

namespace App\Http\Controllers\Cadete;

use App\CadetePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class CadetePaymentController extends Controller
{
    /**
     * GET /api/cadete/payments - Obtener pagos del cadete autenticado
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'payment_type' => 'nullable|string|in:monthly,biweekly,weekly,bonus,advance,other',
            'status' => 'nullable|string|in:pending,paid,cancelled',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = CadetePayment::with(['admin:id,name,email'])
            ->where('cadete_id', Auth::id());

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
            'message' => 'Pagos obtenidos correctamente',
            'data' => $payments->items(),
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
                'statuses' => CadetePayment::getStatuses(),
            ]
        ]);
    }

    /**
     * GET /api/cadete/payments/{id} - Obtener un pago específico del cadete
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
        
        // Verificar que el pago pertenezca al cadete autenticado
        if ($payment->cadete_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes acceso a este pago'
            ], 403);
        }

        $payment->load(['admin:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Pago obtenido correctamente',
            'data' => $payment
        ]);
    }

    /**
     * GET /api/cadete/payments/summary - Resumen de pagos del cadete
     */
    public function getPaymentsSummary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $cadeteId = Auth::id();
        $cadete = Auth::user();
        
        // Obtener porcentaje de comisión del cadete
        $commissionPercentage = $cadete->commission_percentage ?? 0;

        // Obtener comisiones del cadete
        $commissionsQuery = \App\Shared\Models\Commission::where('cadete_id', $cadeteId);
        
        // Aplicar filtro de fecha si se proporciona
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $commissionsQuery->whereBetween('date', [
                \Carbon\Carbon::parse($request->date_from)->startOfDay(),
                \Carbon\Carbon::parse($request->date_to)->endOfDay()
            ]);
        }

        $commissions = $commissionsQuery->get();

        // Calcular métricas de comisiones usando reduce para sumar correctamente
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $deliveredCommissions = $commissions->filter(function ($commission) {
            return $commission->status === \App\Shared\Enums\CommissionStatus::ENTREGADO 
                || $commission->status === \App\Shared\Enums\CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === \App\Shared\Enums\CommissionStatus::PENDIENTE_PAGO
                || $commission->status === \App\Shared\Enums\CommissionStatus::PAGO_VALIDACION
                || $commission->status === \App\Shared\Enums\CommissionStatus::PAGO_CONFIRMADO;
        });
        
        $pendingCommissions = $commissions->filter(function ($commission) {
            return $commission->status !== \App\Shared\Enums\CommissionStatus::ENTREGADO 
                && $commission->status !== \App\Shared\Enums\CommissionStatus::RETIRADO_SUCURSAL
                && $commission->status !== \App\Shared\Enums\CommissionStatus::PENDIENTE_PAGO
                && $commission->status !== \App\Shared\Enums\CommissionStatus::PAGO_VALIDACION
                && $commission->status !== \App\Shared\Enums\CommissionStatus::PAGO_CONFIRMADO
                && $commission->status !== \App\Shared\Enums\CommissionStatus::CANCELADO;
        });

        // Ganancias totales (comisiones finalizadas) - usar reduce para sumar correctamente
        $totalCommissionAmount = $deliveredCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $totalEarnings = $totalCommissionAmount * ($commissionPercentage / 100);
        
        // Cantidad de entregas
        $deliveriesCount = $deliveredCommissions->count();
        
        // Promedio por entrega
        $averagePerDelivery = $deliveriesCount > 0 ? $totalEarnings / $deliveriesCount : 0;
        
        // Pendiente (comisiones no entregadas)
        $pendingCommissionAmount = $pendingCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $pendingEarnings = $pendingCommissionAmount * ($commissionPercentage / 100);
        
        // Ganancias últimos 7 días
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $last7Days = now()->subDays(7);
        $last7DaysCommissions = $commissions->filter(function ($commission) use ($last7Days) {
            return ($commission->status === \App\Shared\Enums\CommissionStatus::ENTREGADO 
                || $commission->status === \App\Shared\Enums\CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === \App\Shared\Enums\CommissionStatus::PENDIENTE_PAGO
                || $commission->status === \App\Shared\Enums\CommissionStatus::PAGO_VALIDACION
                || $commission->status === \App\Shared\Enums\CommissionStatus::PAGO_CONFIRMADO)
                && $commission->updated_at >= $last7Days;
        });
        $last7DaysCommissionAmount = $last7DaysCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $last7DaysEarnings = $last7DaysCommissionAmount * ($commissionPercentage / 100);
        
        // Comisión promedio
        $totalAllCommissions = $commissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        $averageCommission = $commissions->count() > 0 ? $totalAllCommissions / $commissions->count() : 0;
        
        // Entregas hoy
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $todayDeliveries = $commissions
            ->filter(function ($commission) {
                return ($commission->status === \App\Shared\Enums\CommissionStatus::ENTREGADO 
                    || $commission->status === \App\Shared\Enums\CommissionStatus::RETIRADO_SUCURSAL
                    || $commission->status === \App\Shared\Enums\CommissionStatus::PENDIENTE_PAGO
                    || $commission->status === \App\Shared\Enums\CommissionStatus::PAGO_VALIDACION
                    || $commission->status === \App\Shared\Enums\CommissionStatus::PAGO_CONFIRMADO)
                    && $commission->updated_at >= now()->startOfDay();
            })
            ->count();
        
        // Próximo pago (primer pago pendiente)
        $nextPayment = CadetePayment::where('cadete_id', $cadeteId)
            ->where('status', CadetePayment::STATUS_PENDING)
            ->orderBy('payment_date', 'asc')
            ->first();
        
        $nextPaymentAmount = $nextPayment ? $nextPayment->net_amount : 0;

        // Resumen de pagos (mantener compatibilidad)
        $paymentsQuery = CadetePayment::where('cadete_id', $cadeteId);
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $paymentsQuery->byPeriod($request->date_from, $request->date_to);
        }
        $payments = $paymentsQuery->get();

        $summary = [
            // Métricas principales de ganancias
            'total_earnings' => round($totalEarnings, 2),
            'deliveries_count' => $deliveriesCount,
            'average_per_delivery' => round($averagePerDelivery, 2),
            'pending_earnings' => round($pendingEarnings, 2),
            'last_7_days_earnings' => round($last7DaysEarnings, 2),
            'average_commission' => round($averageCommission, 2),
            'today_deliveries' => $todayDeliveries,
            'next_payment_amount' => round($nextPaymentAmount, 2),
            
            // Resumen de comisiones por estado
            'commissions_summary' => [
                'total_commissions' => $commissions->count(),
                'delivered_commissions' => $deliveredCommissions->count(),
                'pending_commissions' => $pendingCommissions->count(),
                'cancelled_commissions' => $commissions->filter(function ($commission) {
                    return $commission->status === \App\Shared\Enums\CommissionStatus::CANCELADO;
                })->count(),
            ],
            
            // Resumen de pagos (compatibilidad)
            'payments_summary' => [
                'total_payments' => $payments->count(),
                'total_amount' => $payments->sum('net_amount'),
                'pending_payments' => $payments->where('status', CadetePayment::STATUS_PENDING)->count(),
                'pending_amount' => $payments->where('status', CadetePayment::STATUS_PENDING)->sum('net_amount'),
                'paid_payments' => $payments->where('status', CadetePayment::STATUS_PAID)->count(),
                'paid_amount' => $payments->where('status', CadetePayment::STATUS_PAID)->sum('net_amount'),
                'cancelled_payments' => $payments->where('status', CadetePayment::STATUS_CANCELLED)->count(),
                'cancelled_amount' => $payments->where('status', CadetePayment::STATUS_CANCELLED)->sum('net_amount'),
            ],
            
            // Configuración del cadete
            'cadete_info' => [
                'commission_percentage' => $commissionPercentage,
                'name' => $cadete->name,
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Resumen de ganancias obtenido correctamente',
            'data' => $summary
        ]);
    }

    /**
     * GET /api/cadete/payments/next-payment - Obtener información del próximo pago
     */
    public function getNextPayment(): JsonResponse
    {
        $nextPayment = CadetePayment::where('cadete_id', Auth::id())
            ->where('status', CadetePayment::STATUS_PENDING)
            ->orderBy('payment_date', 'asc')
            ->first();

        if (!$nextPayment) {
            return response()->json([
                'success' => true,
                'message' => 'No hay pagos pendientes',
                'data' => null
            ]);
        }

        $nextPayment->load(['admin:id,name,email']);

        return response()->json([
            'success' => true,
            'message' => 'Próximo pago obtenido correctamente',
            'data' => $nextPayment
        ]);
    }

    /**
     * GET /api/cadete/payments/recent - Obtener pagos recientes
     */
    public function getRecentPayments(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $limit = $request->get('limit', 10);

        $recentPayments = CadetePayment::where('cadete_id', Auth::id())
            ->orderBy('payment_date', 'desc')
            ->limit($limit)
            ->get(['id', 'payment_type', 'payment_method', 'net_amount', 'status', 'payment_date', 'period_start', 'period_end']);

        return response()->json([
            'success' => true,
            'message' => 'Pagos recientes obtenidos correctamente',
            'data' => $recentPayments
        ]);
    }
}
