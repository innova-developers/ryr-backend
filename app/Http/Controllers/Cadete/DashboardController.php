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
        $user = Auth::user();
        $summary = $this->calculateSummary($commissions);
        $earnings = $this->calculateEarnings($commissions, $user);
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
        $completed = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL;
        })->count();
        $pending = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::CADETE_ASIGNADO
                || $commission->status === CommissionStatus::CADETE_EN_CAMINO_ORIGEN
                || $commission->status === CommissionStatus::EN_PUNTO_RETIRO
                || $commission->status === CommissionStatus::ENCOMIENDA_RETIRADA
                || $commission->status === CommissionStatus::EN_CAMINO_PLANTA
                || $commission->status === CommissionStatus::EN_TRANSITO_DESTINO
                || $commission->status === CommissionStatus::EN_PROCESO_ENTREGA;
        })->count();
        $cancelled = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::CANCELADO;
        })->count();
        
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
    private function calculateEarnings($commissions, $user): array
    {
        // Obtener porcentaje de comisión del cadete
        $commissionPercentage = $user->commission_percentage ?? 0;
        
        // Calcular total de todas las comisiones del día
        $totalCommissionAmount = $commissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        
        // Calcular ganancias totales aplicando el porcentaje
        $totalEarnings = $totalCommissionAmount * ($commissionPercentage / 100);
        
        // Calcular ganancias de comisiones entregadas (cash)
        // Incluir estados: ENTREGADO, RETIRADO_SUCURSAL, PENDIENTE_PAGO, PAGO_VALIDACION, PAGO_CONFIRMADO
        $deliveredCommissions = $commissions->filter(function ($commission) {
            return $commission->status === CommissionStatus::ENTREGADO 
                || $commission->status === CommissionStatus::RETIRADO_SUCURSAL
                || $commission->status === CommissionStatus::PENDIENTE_PAGO
                || $commission->status === CommissionStatus::PAGO_VALIDACION
                || $commission->status === CommissionStatus::PAGO_CONFIRMADO;
        });
        
        $cashCommissionAmount = $deliveredCommissions->reduce(function ($carry, $commission) {
            return $carry + (float) $commission->total;
        }, 0);
        
        // Calcular ganancias de cash aplicando el porcentaje
        $cashEarnings = $cashCommissionAmount * ($commissionPercentage / 100);
        
        return [
            'today_total' => round($totalEarnings, 2),
            'today_cash' => round($cashEarnings, 2),
            'today_card' => 0, // TODO: Implementar lógica de método de pago
            'currency' => 'ARS',
            'formatted_total' => '$' . number_format($totalEarnings, 0, ',', '.')
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
