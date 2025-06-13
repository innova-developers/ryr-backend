<?php

namespace App\Contexts\Commissions\Infrastructure\Repositories;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Shared\Models\Commission;
use App\Shared\Models\CommissionItem;
use App\Shared\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use App\Shared\Enums\CommissionStatus;

class CommissionsEloquentRepository implements CommissionsRepository
{
    /**
     * @throws \Exception
     */
    public function create(CreateCommissionDTO $dto, int $destinationId): Commission
    {
        try{
            $user = User::find(Auth::id());
            $commission = new Commission();
            $commission->client_id = $dto->clientId;
            $commission->destination_id = $destinationId;
            $commission->date = $dto->date;
            $commission->status = $dto->status;
            $commission->user_id = $user->id;
            $commission->branch_id = $user->branch_id;
            $commission->total = $dto->total;
            $commission->save();
            return $commission;
        } catch (\Exception $e) {
            throw new \Exception('Error al crear comisión: ' . $e->getMessage());
        }
    }
    public function addItems(int $commissionId, array $items): void
    {
        try{
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
        }catch (\Exception $e){
            throw new \Exception('Error al agregar items a la comisión: ' . $e->getMessage());
        }

    }

    /**
     * @throws \Exception
     */
    public function findById(int $id): Commission
    {
        $commission = Commission::find($id);
        if (!$commission) {
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
            $query = Commission::with(['items', 'client', 'destination', 'user', 'branch']);

            if ($filters->client) {
                $query->whereHas('client', function($q) use ($filters) {
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

            return $query->orderBy('created_at', 'desc')
                ->paginate($filters->perPage, ['*'], 'page', $filters->page);
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
            if (!$commission) {
                throw new \Exception('Comisión no encontrada');
            }

            $commission->items()->delete();
            $commission->delete();
        } catch (\Exception $e) {
            throw new \Exception('Error al eliminar la comisión: ' . $e->getMessage());
        }
    }
    public function updateStatus(int $id, CommissionStatus $status): void
    {
        try {
            $commission = Commission::find($id);
            if (!$commission) {
                throw new \Exception('Comisión no encontrada');
            }
            $commission->status = $status;
            $commission->save();
        } catch (\Exception $e) {
            throw new \Exception('Error al actualizar el estado de la comisión: ' . $e->getMessage());
        }
    }
}
