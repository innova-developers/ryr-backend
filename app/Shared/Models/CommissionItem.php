<?php

namespace App\Shared\Models;

use App\Shared\Enums\CommissionItemSize;
use App\Shared\Enums\CommissionItemType;
use Database\Factories\CommissionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionItem extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'commission_id',
        'type',
        'size',
        'quantity',
        'unit_price',
        'subtotal',
        'detail'
    ];

    protected $casts = [
        'type' => CommissionItemType::class,
        'size' => CommissionItemSize::class,
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public static function newFactory(): CommissionItemFactory
    {
        return CommissionItemFactory::new();
    }
}
