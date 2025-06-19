<?php

namespace App\Shared\Models;

use Database\Factories\ExtraordinaryCommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExtraordinaryCommission extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = [
        'origin',
        'destination',
        'detail',
        'price',
        'observations',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public static function newFactory(): ExtraordinaryCommissionFactory
    {
        return ExtraordinaryCommissionFactory::new();
    }
}
