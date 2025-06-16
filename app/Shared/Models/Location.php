<?php

namespace App\Shared\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'origin',
        'phone',
        'map',
        'schedule',
        'observation',
    ];

    public static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
