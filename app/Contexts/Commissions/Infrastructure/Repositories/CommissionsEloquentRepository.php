<?php

namespace App\Contexts\Commissions\Infrastructure\Repositories;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\CommissionLog;
use App\Shared\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CommissionsEloquentRepository implements CommissionsRepository
{
    /**
     * @throws \Exception
     */
    public function create(CreateCommissionDTO $dto, int $destinationId): Commission
    {
        try {
            $user = User::find(Auth::id());
            $commission = new Commission();
            $commission->client_id = $dto->clientId;
            $commission->destination_id = $destinationId;
            $commission->date = $dto->date;
            $commission->status = $dto->status;
            $commission->user_id = $user->id;
            $commission->branch_id = $user->branch_id;
            $commission->total = $dto->total;
            $commission->origin_location_id = $dto->originLocationId;
            $commission->destination_location_id = $dto->destinationLocationId;
            $commission->save();

            return $commission;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear comisión: ' . $e->getMessage());
        }
    }
    public function addItems(int $commissionId, array $items): void
    {
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
            ]);

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
}
