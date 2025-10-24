<?php

namespace App\Contexts\Commissions\Infrastructure\Repositories;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Contexts\Commissions\Application\DTOs\UpdateCommissionDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Services\IvaCalculationService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CommissionsEloquentRepository implements CommissionsRepository
{
    public function __construct(
        private IvaCalculationService $ivaCalculationService
    ) {
    }
    /**
     * @throws \Exception
     */
    public function create(CreateCommissionDTO $dto, int $destinationId): Commission
    {
        try {
            $userId = Auth::id() ?? 1; // Usar ID 1 como fallback si no hay usuario autenticado
            $user = User::find($userId);
            $commission = new Commission();
            $commission->client_id = $dto->clientId;
            $commission->destination_id = $destinationId;
            $commission->date = $dto->date;
            $commission->status = $dto->status;
            $commission->user_id = $userId;
            $commission->branch_id = $user?->branch_id ?? 1; // Usar branch_id 1 como fallback
            $commission->total = $dto->total;
            $commission->notes = $dto->notes;
            $commission->origin_location_id = $dto->originLocationId;
            $commission->destination_location_id = $dto->destinationLocationId;
            
            // Aplicar lógica de IVA si hay método de pago
            if (isset($dto->paymentMethod)) {
                $customer = \App\Shared\Models\Customer::find($dto->clientId);
                $ivaCalculation = $this->ivaCalculationService->calculateIva($customer, $dto->paymentMethod, $dto->total);
                
                $commission->payment_method = $dto->paymentMethod;
                $commission->iva_amount = $ivaCalculation['iva_amount'];
                $commission->iva_applied = $ivaCalculation['iva_applied'];
                $commission->total = $ivaCalculation['total_with_iva'];
                
                // Agregar nota sobre IVA si se aplicó
                if ($ivaCalculation['iva_applied']) {
                    $ivaNote = $this->ivaCalculationService->generateIvaNote($ivaCalculation['iva_amount'], true);
                    $commission->notes = $commission->notes ? $commission->notes . "\n" . $ivaNote : $ivaNote;
                }
            }
            
            $commission->save();

            return $commission;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear comisión: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function update(UpdateCommissionDTO $dto, int $destinationId): void
    {
        try {
            $commission = Commission::findOrFail($dto->id);
            $previousPaymentMethod = $commission->payment_method;
            
            $commission->client_id = $dto->clientId;
            $commission->destination_id = $destinationId;
            $commission->date = $dto->date;
            $commission->status = $dto->status;
            $commission->total = $dto->total;
            $commission->notes = $dto->notes;
            $commission->origin_location_id = $dto->originLocationId;
            $commission->destination_location_id = $dto->destinationLocationId;
            
            // Aplicar lógica de IVA si hay método de pago
            if (isset($dto->paymentMethod)) {
                $customer = \App\Shared\Models\Customer::find($dto->clientId);
                
                // Si cambió el método de pago, recalcular IVA
                if ($previousPaymentMethod !== $dto->paymentMethod) {
                    $commission = $this->ivaCalculationService->updateIvaForPaymentMethodChange($commission, $dto->paymentMethod);
                } else {
                    // Si no cambió, solo aplicar IVA si corresponde
                    $ivaCalculation = $this->ivaCalculationService->calculateIva($customer, $dto->paymentMethod, $dto->total);
                    $commission->payment_method = $dto->paymentMethod;
                    $commission->iva_amount = $ivaCalculation['iva_amount'];
                    $commission->iva_applied = $ivaCalculation['iva_applied'];
                    $commission->total = $ivaCalculation['total_with_iva'];
                }
                
                // Agregar nota sobre IVA si se aplicó
                if ($commission->iva_applied && $commission->iva_amount > 0) {
                    $ivaNote = $this->ivaCalculationService->generateIvaNote($commission->iva_amount, true);
                    $commission->notes = $commission->notes ? $commission->notes . "\n" . $ivaNote : $ivaNote;
                }
            }
            
            $commission->save();
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar comisión: ' . $e->getMessage());
        }
    }

    public function addItems(int $commissionId, ?array $items): void
    {
        if ($items === null || empty($items)) {
            return;
        }

        try {
            foreach ($items as $item) {
                $commissionItem = new CommissionItem();
                $commissionItem->commission_id = $commissionId;
                $commissionItem->type = $item->type;
                $commissionItem->size = $item->size;
                $commissionItem->quantity = $item->quantity;
                $commissionItem->unit_price = $item->unitPrice;
                $commissionItem->subtotal = $item->subtotal;
                $commissionItem->detail = $item->detail;
                $commissionItem->save();
            }
        } catch (\Exception $e) {
            throw new \Exception('Error al agregar items a la comisión: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function deleteItems(int $commissionId): void
    {
        try {
            CommissionItem::where('commission_id', $commissionId)->forceDelete();
        } catch (\Exception $e) {
            throw new \Exception('Error al eliminar items de la comisión: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function findById(int $id): Commission
    {
        $commission = Commission::with([
            'items',
            'client',
            'destination',
            'user',
            'branch',
            'originLocation',
            'destinationLocation',
            'logs.user',
            'cadete',
            'deliverySignature',
        ])->find($id);

        if (! $commission) {
            throw new \Exception('Comisión no encontrada');
        }

        return $commission;
    }

    /**
     * @throws \Exception
     */
    public function findAllWithItems(ListCommissionsFiltersDTO $filters): LengthAwarePaginator
    {
        try {
            $query = Commission::with([
                'items',
                'client',
                'destination',
                'user',
                'branch',
                'originLocation',
                'destinationLocation',
                'logs.user',
                'logs.user.branch',
                'cadete',
                'deliverySignature',
            ]);

            // Si se proporciona commissionId, ignorar todos los demás filtros
            if ($filters->commissionId) {
                $query->where('commissions.id', $filters->commissionId);
            } else {
                // Aplicar filtros solo si no se proporciona commissionId
                if ($filters->clientId) {
                    $query->where('client_id', $filters->clientId);
                }

                if ($filters->client) {
                    $query->whereHas('client', function ($q) use ($filters) {
                        $q->where('name', 'LIKE', "%{$filters->client}%")
                          ->orWhere('last_name', 'LIKE', "%{$filters->client}%");
                    });
                }

                if ($filters->destinationId) {
                    $query->where('destination_id', $filters->destinationId);
                }

                if ($filters->branchId) {
                    $query->where('branch_id', $filters->branchId);
                }

                if ($filters->userId) {
                    $query->where('user_id', $filters->userId);
                }

                if ($filters->dateFrom) {
                    $query->where('date', '>=', $filters->dateFrom);
                }

                if ($filters->dateTo) {
                    $query->where('date', '<=', $filters->dateTo);
                }

                if ($filters->status) {
                    $query->where('status', $filters->status->value);
                }

                if ($filters->method) {
                    $query->where('payment_method', $filters->method->value);
                }

                // Aplicar ordenamiento solo si no se proporciona commissionId
                switch ($filters->sort) {
                    case 'id':
                        $query->orderBy('commissions.id', $filters->sortDirection);
                        break;
                    case 'client_name':
                        $query->leftJoin('customers', 'customers.id', '=', 'commissions.client_id')
                            ->orderBy('customers.name', $filters->sortDirection)
                            ->select('commissions.*');
                        break;
                    case 'origin':
                        $query->leftJoin('destinations', 'destinations.id', '=', 'commissions.destination_id')
                            ->orderBy('destinations.origin', $filters->sortDirection)
                            ->select('commissions.*');
                        break;
                    case 'destination':
                        $query->leftJoin('destinations', 'destinations.id', '=', 'commissions.destination_id')
                            ->orderBy('destinations.destination', $filters->sortDirection)
                            ->select('commissions.*');
                        break;
                    case 'date':
                        $query->orderBy('date', $filters->sortDirection);
                        break;
                    case 'total':
                        $query->orderBy('total', $filters->sortDirection);
                        break;
                    case 'status':
                        $query->orderBy('status', $filters->sortDirection);
                        break;
                }
            }

            return $query->paginate($filters->perPage, ['*'], 'page', $filters->page);
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener las comisiones: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function delete(int $id): void
    {
        try {
            $commission = Commission::find($id);
            if (! $commission) {
                throw new \Exception('Comisión no encontrada');
            }
            $commission->delete();
        } catch (\Exception $e) {
            throw new \Exception('Error al eliminar la comisión: ' . $e->getMessage());
        }
    }
    public function updateStatus(int $id, CommissionStatus $status): void
    {
        try {
            $commission = Commission::find($id);
            if (! $commission) {
                throw new \Exception('Comisión no encontrada');
            }
            $commission->status = $status;
            $commission->save();
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar el estado de la comisión: ' . $e->getMessage());
        }
    }

    /**
     * @throws \Exception
     */
    public function createLog(CreateCommissionLogDTO $dto): void
    {
        try {
            CommissionLog::create([
                'commission_id' => $dto->commissionId,
                'user_id' => $dto->userId,
                'previous_status' => $dto->previousStatus,
                'new_status' => $dto->newStatus,
                'details' => $dto->details,
            ]);
        } catch (\Exception $e) {
            throw new \Exception('Error al crear el log de la comisión: ' . $e->getMessage());
        }
    }

    /**
     * Obtener totalizadores para el dashboard
     */
    public function getTotals(ListCommissionsFiltersDTO $filters): array
    {
        try {
            $query = Commission::query();

            // Si se proporciona commissionId, ignorar todos los demás filtros
            if ($filters->commissionId) {
                $query->where('commissions.id', $filters->commissionId);
            } else {
                // Aplicar filtros solo si no se proporciona commissionId
                if ($filters->clientId) {
                    $query->where('client_id', $filters->clientId);
                }

                if ($filters->client) {
                    $query->whereHas('client', function ($q) use ($filters) {
                        $q->where('name', 'LIKE', "%{$filters->client}%")
                          ->orWhere('last_name', 'LIKE', "%{$filters->client}%");
                    });
                }

                if ($filters->destinationId) {
                    $query->where('destination_id', $filters->destinationId);
                }

                if ($filters->branchId) {
                    $query->where('branch_id', $filters->branchId);
                }

                if ($filters->userId) {
                    $query->where('user_id', $filters->userId);
                }

                if ($filters->dateFrom) {
                    $query->where('date', '>=', $filters->dateFrom);
                }

                if ($filters->dateTo) {
                    $query->where('date', '<=', $filters->dateTo);
                }

                if ($filters->status) {
                    $query->where('status', $filters->status->value);
                }

                if ($filters->method) {
                    $query->where('payment_method', $filters->method->value);
                }
            }

            // Total de monto
            $totalAmount = $query->sum('total');

            // Totalizadores por método de pago
            $paymentMethods = $query->clone()
                ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
                ->groupBy('payment_method')
                ->get()
                ->map(function ($item) {
                    return [
                        'method' => $item->payment_method,
                        'count' => $item->count,
                        'total' => (float) $item->total,
                    ];
                });

            // Totalizadores por estado
            $statuses = $query->clone()
                ->selectRaw('status, COUNT(*) as count, SUM(total) as total')
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => $item->status,
                        'count' => $item->count,
                        'total' => (float) $item->total,
                    ];
                });

            // Totalizadores por sucursal
            $branches = $query->clone()
                ->join('branches', 'branches.id', '=', 'commissions.branch_id')
                ->selectRaw('branches.id, branches.name, COUNT(*) as count, SUM(commissions.total) as total')
                ->groupBy('branches.id', 'branches.name')
                ->get()
                ->map(function ($item) {
                    return [
                        'branch_id' => $item->id,
                        'branch_name' => $item->name,
                        'count' => $item->count,
                        'total' => (float) $item->total,
                    ];
                });

            return [
                'total_amount' => (float) $totalAmount,
                'payment_methods' => $paymentMethods,
                'statuses' => $statuses,
                'branches' => $branches,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener totalizadores: ' . $e->getMessage());
        }
    }
}
