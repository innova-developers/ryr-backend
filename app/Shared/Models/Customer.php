<?php

namespace App\Shared\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'dni',
        'name',
        'last_name',
        'mobile',
        'email',
        'address',
        'city',
        'phone',
        'maps_url',
        'business_hours',
        'observations',
        'is_premium',
        'user_id',
        'branch_id',
    ];

    protected $casts = [
        'is_premium' => 'boolean',
        'dni' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->name} {$this->last_name}";
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }
}
