<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tarifa especial de un cliente (RC-484).
 *
 * Cada precio es opcional: lo que queda nulo se resuelve contra la tabla general del
 * destino. Con destination_id nulo la tarifa aplica a todos los destinos.
 */
class CustomerRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'destination_id',
        'fixed_price',
        'small_bulk_price',
        'large_bulk_price',
        'agreement_price',
        'declared_value_percentage',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'fixed_price' => 'float',
        'small_bulk_price' => 'float',
        'large_bulk_price' => 'float',
        'agreement_price' => 'float',
        'declared_value_percentage' => 'float',
        'is_active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(CustomerRateTier::class);
    }
}
