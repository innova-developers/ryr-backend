<?php

namespace App\Shared\Models;

use Database\Factories\DestinationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destination extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'origin',
        'destination',
        'fixed_price',
        'small_bulk_price',
        'large_bulk_price',
    ];

    protected $casts = [
        'fixed_price' => 'float',
        'small_bulk_price' => 'float',
        'large_bulk_price' => 'float',
    ];

    public static function newFactory(): DestinationFactory
    {
        return DestinationFactory::new();
    }
}
