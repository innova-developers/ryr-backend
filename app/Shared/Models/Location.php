<?php

namespace App\Shared\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function originCommissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'origin_location_id');
    }

    public function destinationCommissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'destination_location_id');
    }

    public static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }
}
