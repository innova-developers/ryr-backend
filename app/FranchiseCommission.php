<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FranchiseCommission extends Model
{
    protected $fillable = [
        'franchise_id',
        'commission_id',
        'commission_amount',
        'matrix_commission_amount',
        'commission_percentage',
        'commission_date',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'matrix_commission_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_date' => 'date',
        'paid_at' => 'date',
    ];

    /**
     * Relación con la franquicia
     */
    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    /**
     * Marcar como pagado
     */
    public function markAsPaid(?string $notes = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'notes' => $notes ? ($this->notes ? $this->notes . "\n" . $notes : $notes) : $this->notes,
        ]);
    }

    /**
     * Verificar si está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verificar si está pagado
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
