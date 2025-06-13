<?php

namespace App\Contexts\Commissions\Infrastructure\Repositories;

use App\Contexts\Commissions\Domain\Entities\Commission;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use App\Contexts\Commissions\Domain\DTOs\ListCommissionsFiltersDTO;

class CommissionsRepository
{
    public function findWithAllItems(array $filters = [], ?string $sortBy = null, ?string $sortDirection = 'asc'): LengthAwarePaginator
    {
        $query = Commission::with(['items', 'client', 'destination', 'branch', 'user', 'logs.user'])
            ->when($filters['client_id'] ?? null, function ($query, $clientId) {
                return $query->where('client_id', $clientId);
            })
            ->when($filters['destination_id'] ?? null, function ($query, $destinationId) {
                return $query->where('destination_id', $destinationId);
            })
            ->when($filters['branch_id'] ?? null, function ($query, $branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                return $query->where('date', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                return $query->where('date', '<=', $dateTo);
            });

        if ($sortBy) {
            $query->orderBy($sortBy, $sortDirection ?? 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findAllWithItems(ListCommissionsFiltersDTO $filters): LengthAwarePaginator
    {
        $query = Commission::with(['items', 'client', 'destination', 'branch', 'user', 'logs.user'])
            ->when($filters->clientId, function ($query, $clientId) {
                return $query->where('client_id', $clientId);
            })
            ->when($filters->destinationId, function ($query, $destinationId) {
                return $query->where('destination_id', $destinationId);
            })
            ->when($filters->branchId, function ($query, $branchId) {
                return $query->where('branch_id', $branchId);
            })
            ->when($filters->status, function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($filters->dateFrom, function ($query, $dateFrom) {
                return $query->where('date', '>=', $dateFrom);
            })
            ->when($filters->dateTo, function ($query, $dateTo) {
                return $query->where('date', '<=', $dateTo);
            })
            ->orderBy('created_at', 'desc');

        return $query->paginate($filters->perPage ?? 15);
    }
} 