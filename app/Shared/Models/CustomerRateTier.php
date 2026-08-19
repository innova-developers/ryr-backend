<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escalón de precio por cantidad/volumen dentro de una tarifa especial (RC-484).
 *
 * "A partir de min_quantity bultos de este tamaño, el unitario es unit_price".
 */
class CustomerRateTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_rate_id',
        'size',
        'min_quantity',
        'unit_price',
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'unit_price' => 'float',
    ];

    public function rate(): BelongsTo
    {
        return $this->belongsTo(CustomerRate::class, 'customer_rate_id');
    }

    public function setSizeAttribute($value): void
    {
        $this->attributes['size'] = mb_strtoupper(trim((string) $value));
    }
}
