<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLocation extends Model
{
    protected $fillable = [
        'commission_id',
        'cadete_id',
        'latitude',
        'longitude',
        'address',
        'observation',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'recorded_at' => 'datetime',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function cadete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadete_id');
    }
}
