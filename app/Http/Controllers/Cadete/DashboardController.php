<?php

namespace App\Http\Controllers\Cadete;

use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\Transport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * GET /cadete/home - Dashboard principal del cadete
     */
    public function home(Request $request): JsonResponse
    {
        $user = Auth::user();
        $date = $request->get('date', now()->format('Y-m-d'));
        
        // Obtener comisiones del cadete directamente por cadete_id
        $commissions = Commission::where('cadete_id', $user->id)
                                ->whereDate('date', $date)
                                ->get();
        
        if ($commissions->isEmpty()) {
            return $this->emptyDashboardResponse();
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Dashboard cargado exitosamente',
            'data' => $this->buildDashboardData($commissions)
        ]);
    }
    
    /**
     * Construir datos del dashboard
     */
    private function buildDashboardData($commissions): array
    {
        $summary = $this->calculateSummary($commissions);
        $earnings = $this->calculateEarnings($commissions);
        $performance = $this->calculatePerformance($summary);
        
        return [
            'summary' => $summary,
            'earnings' => $earnings,
            'performance' => $performance,
            'stats' => $this->getStats(),
            'quick_actions' => $this->getQuickActions($summary['total_deliveries']),
            'notifications' => $this->getNotifications()
        ];
    }
    
    /**
     * Calcular resumen de entregas
     */
    private function calculateSummary($commissions): array
    {
        $total = $commissions->count();
        $completed = $commissions->where('status', CommissionStatus::ENTREGADO)->count();
        $pending = $commissions->whereIn('status', [
            CommissionStatus::CADETE_ASIGNADO,
            CommissionStatus::CADETE_EN_CAMINO_ORIGEN,
            CommissionStatus::EN_PUNTO_RETIRO,
            CommissionStatus::ENCOMIENDA_RETIRADA,
            CommissionStatus::EN_CAMINO_PLANTA,
            CommissionStatus::EN_TRANSITO_DESTINO,
            CommissionStatus::EN_PROCESO_ENTREGA
        ])->count();
        $cancelled = $commissions->where('status', CommissionStatus::CANCELADO)->count();
        
        return [
            'total_deliveries' => $total,
            'completed_deliveries' => $completed,
            'pending_deliveries' => $pending,
            'cancelled_deliveries' => $cancelled
        ];
    }
    
    /**
     * Calcular ganancias
     */
    private function calculateEarnings($commissions): array
    {
        $total = $commissions->sum('total');
        $cash = $commissions->where('status', CommissionStatus::ENTREGADO)->sum('total');
        
        return [
            'today_total' => $total,
            'today_cash' => $cash,
            'today_card' => 0, // TODO: Implementar lógica de método de pago
            'currency' => 'ARS',
            'formatted_total' => '$' . number_format($total, 0, ',', '.')
        ];
    }
    
    /**
     * Calcular métricas de rendimiento
     */
    private function calculatePerformance($summary): array
    {
        $successRate = $summary['total_deliveries'] > 0 
            ? round(($summary['completed_deliveries'] / $summary['total_deliveries']) * 100, 1)
            : 0.0;
            
        return [
            'delivery_success_rate' => $successRate,
            'average_delivery_time' => '25', // TODO: Implementar cálculo real
            'time_unit' => 'minutes',
            'rating_today' => 4.8, // TODO: Implementar sistema de ratings
            'total_ratings' => 8
        ];
    }
    
    /**
     * Obtener estadísticas (por ahora hardcodeadas)
     */
    private function getStats(): array
    {
        return [
            'distance_covered_km' => 45.5, // TODO: Implementar tracking real
            'fuel_consumption' => '2.3L',
            'active_hours' => 8.5
        ];
    }
    
    /**
     * Obtener acciones rápidas
     */
    private function getQuickActions(int $totalDeliveries): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Nueva entrega',
                'action' => 'new_delivery',
                'icon' => 'add_circle',
                'enabled' => $totalDeliveries > 0
            ],
            [
                'id' => 2,
                'title' => 'Ver historial',
                'action' => 'view_history',
                'icon' => 'history',
                'enabled' => true
            ]
        ];
    }
    
    /**
     * Obtener notificaciones (por ahora vacías)
     */
    private function getNotifications(): array
    {
        return [
            'unread_count' => 0,
            'latest' => []
        ];
    }
    
    /**
     * Respuesta para dashboard vacío
     */
    private function emptyDashboardResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'No tienes comisiones asignadas para este día',
            'data' => [
                'summary' => [
                    'total_deliveries' => 0,
                    'completed_deliveries' => 0,
                    'pending_deliveries' => 0,
                    'cancelled_deliveries' => 0
                ],
                'earnings' => [
                    'today_total' => 0,
                    'today_cash' => 0,
                    'today_card' => 0,
                    'currency' => 'ARS',
                    'formatted_total' => '$0'
                ],
                'performance' => [
                    'delivery_success_rate' => 0.0,
                    'average_delivery_time' => '0',
                    'time_unit' => 'minutes',
                    'rating_today' => 0.0,
                    'total_ratings' => 0
                ],
                'stats' => [
                    'distance_covered_km' => 0.0,
                    'fuel_consumption' => '0L',
                    'active_hours' => 0.0
                ],
                'quick_actions' => [
                    [
                        'id' => 1,
                        'title' => 'Nueva entrega',
                        'action' => 'new_delivery',
                        'icon' => 'add_circle',
                        'enabled' => false
                    ],
                    [
                        'id' => 2,
                        'title' => 'Ver historial',
                        'action' => 'view_history',
                        'icon' => 'history',
                        'enabled' => true
                    ]
                ],
                'notifications' => [
                    'unread_count' => 0,
                    'latest' => []
                ]
            ]
        ]);
    }
}
