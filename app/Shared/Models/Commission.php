<?php

namespace App\Shared\Models;

use App\Shared\Enums\CommissionStatus;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'destination_id',
        'branch_id',
        'date',
        'status',
        'total',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => CommissionStatus::class,
        'total' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommissionItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CommissionLog::class);
    }

    public static function newFactory(): CommissionFactory
    {
        return CommissionFactory::new();
    }
}
