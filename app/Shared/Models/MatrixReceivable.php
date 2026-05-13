<?php

namespace App\Shared\Models;

use Database\Factories\MatrixReceivableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MatrixReceivable extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'franchise_id',
        'commission_id',
        'amount',
        'percentage_applied',
        'commission_total',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage_applied' => 'decimal:2',
        'commission_total' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public static function newFactory(): MatrixReceivableFactory
    {
        return MatrixReceivableFactory::new();
    }
}
