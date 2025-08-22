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

        $query = CadetePayment::where('cadete_id', Auth::id());

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
            ->select('payment_type', \DB::raw('COUNT(*) as count'), \DB::raw('SUM(net_amount) as total_amount'))
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
