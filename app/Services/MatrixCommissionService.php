<?php

namespace App\Services;

use App\Franchise;
use App\FranchiseCommission;
use App\Shared\Models\Commission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatrixCommissionService
{
    /**
     * Calcular y guardar la comisión de la matriz cuando una comisión de franquicia pasa a PAGO_CONFIRMADO
     * 
     * @param int $commissionId ID de la comisión en la base de datos de la franquicia
     * @param int|null $franchiseId ID de la franquicia (si no se proporciona, se intenta obtener de la sesión)
     * @return FranchiseCommission|null
     */
    public function calculateAndStoreMatrixCommission(int $commissionId, ?int $franchiseId = null): ?FranchiseCommission
    {
        try {
            // Obtener el ID de la franquicia
            if (!$franchiseId) {
                $franchiseId = session('current_franchise_id');
            }

            if (!$franchiseId) {
                Log::warning('No se pudo determinar la franquicia para calcular la comisión de la matriz', [
                    'commission_id' => $commissionId,
                ]);
                return null;
            }

            // Obtener la franquicia desde la base de datos principal
            $franchise = Franchise::on('mysql')->find($franchiseId);
            
            if (!$franchise) {
                Log::warning('Franquicia no encontrada', [
                    'franchise_id' => $franchiseId,
                    'commission_id' => $commissionId,
                ]);
                return null;
            }

            // Verificar que la franquicia tenga un porcentaje de comisión configurado
            $commissionPercentage = $franchise->commission_percentage ?? 0;
            
            if ($commissionPercentage <= 0) {
                Log::info('Franquicia no tiene porcentaje de comisión configurado, no se calcula comisión de matriz', [
                    'franchise_id' => $franchiseId,
                    'commission_id' => $commissionId,
                ]);
                return null;
            }

            // Obtener la comisión desde la base de datos de la franquicia
            // Necesitamos cambiar temporalmente a la conexión de la franquicia
            $franchiseDatabaseService = app(FranchiseDatabaseService::class);
            $franchiseDatabaseService->setFranchiseConnection($franchise);

            $commission = Commission::find($commissionId);
            
            if (!$commission) {
                Log::warning('Comisión no encontrada en la base de datos de la franquicia', [
                    'franchise_id' => $franchiseId,
                    'commission_id' => $commissionId,
                ]);
                $franchiseDatabaseService->restoreMainConnection();
                return null;
            }

            // Verificar que la comisión esté en estado PAGO_CONFIRMADO
            if ($commission->status->value !== 'PAGO_CONFIRMADO') {
                Log::info('Comisión no está en estado PAGO_CONFIRMADO, no se calcula comisión de matriz', [
                    'franchise_id' => $franchiseId,
                    'commission_id' => $commissionId,
                    'status' => $commission->status->value,
                ]);
                $franchiseDatabaseService->restoreMainConnection();
                return null;
            }

            // Verificar si ya existe una comisión de matriz para esta comisión
            $existingMatrixCommission = FranchiseCommission::on('mysql')
                ->where('franchise_id', $franchiseId)
                ->where('commission_id', $commissionId)
                ->first();

            if ($existingMatrixCommission) {
                Log::info('Ya existe una comisión de matriz para esta comisión', [
                    'franchise_id' => $franchiseId,
                    'commission_id' => $commissionId,
                    'matrix_commission_id' => $existingMatrixCommission->id,
                ]);
                $franchiseDatabaseService->restoreMainConnection();
                return $existingMatrixCommission;
            }

            // Calcular la comisión de la matriz
            $commissionAmount = $commission->total;
            $matrixCommissionAmount = ($commissionAmount * $commissionPercentage) / 100;

            // Restaurar la conexión principal antes de guardar
            $franchiseDatabaseService->restoreMainConnection();

            // Guardar la comisión de la matriz en la base de datos principal
            $franchiseCommission = FranchiseCommission::on('mysql')->create([
                'franchise_id' => $franchiseId,
                'commission_id' => $commissionId,
                'commission_amount' => $commissionAmount,
                'matrix_commission_amount' => $matrixCommissionAmount,
                'commission_percentage' => $commissionPercentage,
                'commission_date' => $commission->date,
                'status' => 'pending',
                'notes' => "Comisión calculada automáticamente cuando la comisión #{$commissionId} pasó a PAGO_CONFIRMADO",
            ]);

            Log::info('Comisión de matriz calculada y guardada exitosamente', [
                'franchise_id' => $franchiseId,
                'commission_id' => $commissionId,
                'matrix_commission_id' => $franchiseCommission->id,
                'commission_amount' => $commissionAmount,
                'matrix_commission_amount' => $matrixCommissionAmount,
                'commission_percentage' => $commissionPercentage,
            ]);

            return $franchiseCommission;

        } catch (\Exception $e) {
            Log::error('Error al calcular y guardar la comisión de la matriz', [
                'commission_id' => $commissionId,
                'franchise_id' => $franchiseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Asegurar que se restaure la conexión principal en caso de error
            try {
                $franchiseDatabaseService = app(FranchiseDatabaseService::class);
                $franchiseDatabaseService->restoreMainConnection();
            } catch (\Exception $restoreException) {
                Log::error('Error al restaurar conexión principal', [
                    'error' => $restoreException->getMessage(),
                ]);
            }

            return null;
        }
    }

    /**
     * Obtener el total de comisiones pendientes por franquicia
     */
    public function getPendingCommissionsByFranchise(): array
    {
        $results = FranchiseCommission::on('mysql')
            ->where('status', 'pending')
            ->select('franchise_id', DB::raw('SUM(matrix_commission_amount) as total_amount'), DB::raw('COUNT(*) as total_commissions'))
            ->groupBy('franchise_id')
            ->get();

        // Obtener nombres de franquicias
        $franchiseIds = $results->pluck('franchise_id')->toArray();
        $franchises = Franchise::on('mysql')->whereIn('id', $franchiseIds)->pluck('name', 'id');

        return $results->map(function ($item) use ($franchises) {
            return [
                'franchise_id' => $item->franchise_id,
                'franchise_name' => $franchises[$item->franchise_id] ?? 'N/A',
                'total_amount' => (float) $item->total_amount,
                'total_commissions' => (int) $item->total_commissions,
            ];
        })->toArray();
    }

    /**
     * Obtener todas las comisiones pendientes con detalles
     */
    public function getPendingCommissions(?int $franchiseId = null): array
    {
        $query = FranchiseCommission::on('mysql')
            ->where('status', 'pending')
            ->with('franchise');

        if ($franchiseId) {
            $query->where('franchise_id', $franchiseId);
        }

        return $query->orderBy('commission_date', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'franchise_id' => $item->franchise_id,
                    'franchise_name' => $item->franchise->name ?? 'N/A',
                    'commission_id' => $item->commission_id,
                    'commission_amount' => (float) $item->commission_amount,
                    'matrix_commission_amount' => (float) $item->matrix_commission_amount,
                    'commission_percentage' => (float) $item->commission_percentage,
                    'commission_date' => $item->commission_date->format('Y-m-d'),
                    'status' => $item->status,
                    'notes' => $item->notes,
                    'created_at' => $item->created_at->toISOString(),
                ];
            })
            ->toArray();
    }
}
