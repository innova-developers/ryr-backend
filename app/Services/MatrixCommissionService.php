<?php

namespace App\Services;

use App\Shared\Models\Commission;
use App\Shared\Models\Franchise;
use App\Shared\Models\MatrixReceivable;

class MatrixCommissionService
{
    public function createReceivableForCommission(Commission $commission): ?MatrixReceivable
    {
        if (! $commission->franchise_id) {
            return null;
        }

        $franchise = Franchise::find($commission->franchise_id);
        if (! $franchise || $franchise->commission_percentage_to_matrix <= 0) {
            return null;
        }

        $existing = MatrixReceivable::where('commission_id', $commission->id)->first();
        if ($existing) {
            return $existing;
        }

        $amount = $commission->total * ($franchise->commission_percentage_to_matrix / 100);

        return MatrixReceivable::create([
            'franchise_id' => $franchise->id,
            'commission_id' => $commission->id,
            'amount' => round($amount, 2),
            'percentage_applied' => $franchise->commission_percentage_to_matrix,
            'commission_total' => $commission->total,
            'status' => 'pending',
            'due_date' => now()->addDays(30),
        ]);
    }

    public function bulkMarkAsPaid(array $ids, ?string $paymentReference = null): int
    {
        $now = now();

        return MatrixReceivable::whereIn('id', $ids)
            ->where('status', 'pending')
            ->update([
                'status' => 'paid',
                'paid_at' => $now,
                'payment_reference' => $paymentReference,
            ]);
    }

    public function markAsPaid(int $receivableId, ?string $paymentReference = null): MatrixReceivable
    {
        $receivable = MatrixReceivable::findOrFail($receivableId);
        $receivable->status = 'paid';
        $receivable->paid_at = now();
        $receivable->payment_reference = $paymentReference;
        $receivable->save();

        return $receivable;
    }

    public function markAsCancelled(int $receivableId, ?string $notes = null): MatrixReceivable
    {
        $receivable = MatrixReceivable::findOrFail($receivableId);
        $receivable->status = 'cancelled';
        $receivable->notes = $notes;
        $receivable->save();

        return $receivable;
    }

    public function getReceivablesByFranchise(
        ?int $franchiseId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $page = 1,
        int $perPage = 15
    ): array {
        $query = MatrixReceivable::with(['franchise', 'commission.client']);

        if ($franchiseId) {
            $query->where('franchise_id', $franchiseId);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $query->orderBy('created_at', 'desc');
        $result = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $result->items(),
            'pagination' => [
                'current_page' => $result->currentPage(),
                'per_page' => $result->perPage(),
                'total' => $result->total(),
                'last_page' => $result->lastPage(),
            ],
        ];
    }

    public function getSummary(?int $franchiseId = null): array
    {
        $query = MatrixReceivable::query();
        if ($franchiseId) {
            $query->where('franchise_id', $franchiseId);
        }

        $pending = (clone $query)->where('status', 'pending')->sum('amount');
        $paid = (clone $query)->where('status', 'paid')->sum('amount');
        $cancelled = (clone $query)->where('status', 'cancelled')->sum('amount');
        $total = (clone $query)->whereIn('status', ['pending', 'paid'])->sum('amount');

        return [
            'pending_amount' => round($pending, 2),
            'paid_amount' => round($paid, 2),
            'cancelled_amount' => round($cancelled, 2),
            'total_amount' => round($total, 2),
            'pending_count' => (clone $query)->where('status', 'pending')->count(),
            'paid_count' => (clone $query)->where('status', 'paid')->count(),
        ];
    }
}
