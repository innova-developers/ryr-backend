<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Franchise;
use App\Services\FranchiseDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    protected $franchiseDatabaseService;

    public function __construct(FranchiseDatabaseService $franchiseDatabaseService)
    {
        $this->franchiseDatabaseService = $franchiseDatabaseService;
    }

    /**
     * Dashboard principal del super admin
     */
    public function index(): JsonResponse
    {
        try {
            $stats = $this->getGlobalStats();
            $franchises = $this->getFranchisesOverview();
            $recentActivity = $this->getRecentActivity();

            return response()->json([
                'success' => true,
                'data' => [
                    'global_stats' => $stats,
                    'franchises' => $franchises,
                    'recent_activity' => $recentActivity,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estadísticas globales del sistema
     */
    private function getGlobalStats(): array
    {
        $totalFranchises = Franchise::count();
        $activeFranchises = Franchise::where('is_active', true)->count();
        $inactiveFranchises = $totalFranchises - $activeFranchises;

        // Calcular estadísticas agregadas de todas las franquicias
        $totalCommissions = 0;
        $totalCustomers = 0;
        $totalUsers = 0;
        $totalRevenue = 0;

        $franchises = Franchise::where('is_active', true)->get();
        
        foreach ($franchises as $franchise) {
            $stats = $this->franchiseDatabaseService->getFranchiseDatabaseStats($franchise);
            
            $totalCommissions += $stats['commissions_count'] ?? 0;
            $totalCustomers += $stats['customers_count'] ?? 0;
            $totalUsers += $stats['users_count'] ?? 0;
        }

        return [
            'total_franchises' => $totalFranchises,
            'active_franchises' => $activeFranchises,
            'inactive_franchises' => $inactiveFranchises,
            'total_commissions' => $totalCommissions,
            'total_customers' => $totalCustomers,
            'total_users' => $totalUsers,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * Resumen de todas las franquicias
     */
    private function getFranchisesOverview(): array
    {
        $franchises = Franchise::orderBy('created_at', 'desc')->take(10)->get();
        
        $overview = [];
        
        foreach ($franchises as $franchise) {
            $stats = $this->franchiseDatabaseService->getFranchiseDatabaseStats($franchise);
            
            $overview[] = [
                'id' => $franchise->id,
                'name' => $franchise->name,
                'code' => $franchise->code,
                'is_active' => $franchise->is_active,
                'created_at' => $franchise->created_at,
                'stats' => $stats,
                'url' => $franchise->getFullUrl(),
            ];
        }
        
        return $overview;
    }

    /**
     * Actividad reciente del sistema
     */
    private function getRecentActivity(): array
    {
        // Obtener franquicias creadas recientemente
        $recentFranchises = Franchise::orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($franchise) {
                return [
                    'type' => 'franchise_created',
                    'message' => "Nueva franquicia creada: {$franchise->name}",
                    'timestamp' => $franchise->created_at,
                    'data' => $franchise,
                ];
            });

        return $recentFranchises->toArray();
    }

    /**
     * Estadísticas detalladas de una franquicia específica
     */
    public function franchiseStats(Franchise $franchise): JsonResponse
    {
        try {
            $stats = $this->franchiseDatabaseService->getFranchiseDatabaseStats($franchise);
            
            // Obtener estadísticas adicionales si la franquicia está activa
            $additionalStats = [];
            if ($franchise->isActive()) {
                $additionalStats = $this->getFranchiseDetailedStats($franchise);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'franchise' => $franchise,
                    'basic_stats' => $stats,
                    'detailed_stats' => $additionalStats,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error getting franchise stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas detalladas de una franquicia
     */
    private function getFranchiseDetailedStats(Franchise $franchise): array
    {
        return $this->franchiseDatabaseService->executeInFranchiseDatabase($franchise, function () {
            // Estadísticas de comisiones por estado
            $commissionStats = DB::table('commissions')
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray();

            // Estadísticas de comisiones por método de pago
            $paymentMethodStats = DB::table('commissions')
                ->selectRaw('payment_method, COUNT(*) as count')
                ->whereNotNull('payment_method')
                ->groupBy('payment_method')
                ->get()
                ->pluck('count', 'payment_method')
                ->toArray();

            // Comisiones del mes actual
            $currentMonthCommissions = DB::table('commissions')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            // Ingresos del mes actual
            $currentMonthRevenue = DB::table('commissions')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('total');

            return [
                'commission_stats' => $commissionStats,
                'payment_method_stats' => $paymentMethodStats,
                'current_month_commissions' => $currentMonthCommissions,
                'current_month_revenue' => $currentMonthRevenue,
            ];
        });
    }

    /**
     * Reporte consolidado de todas las franquicias
     */
    public function consolidatedReport(): JsonResponse
    {
        try {
            $franchises = Franchise::where('is_active', true)->get();
            $report = [];

            foreach ($franchises as $franchise) {
                $stats = $this->franchiseDatabaseService->getFranchiseDatabaseStats($franchise);
                
                $report[] = [
                    'franchise' => [
                        'id' => $franchise->id,
                        'name' => $franchise->name,
                        'code' => $franchise->code,
                    ],
                    'stats' => $stats,
                    'url' => $franchise->getFullUrl(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'franchises' => $report,
                    'generated_at' => now(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating consolidated report: ' . $e->getMessage(),
            ], 500);
        }
    }
}
