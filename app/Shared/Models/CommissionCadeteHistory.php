<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila por cada tramo de custodia de un cadete sobre una comisión.
 * Registra cada "mano" por la que pasó la comisión.
 */
class CommissionCadeteHistory extends Model
{
    protected $table = 'commission_cadete_history';

    protected $fillable = [
        'commission_id',
        'cadete_id',
        'action',
        'assigned_by',
        'status_at_assignment',
        'assigned_at',
        'released_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function cadete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadete_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
