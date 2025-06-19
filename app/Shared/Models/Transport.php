<?php

namespace App\Shared\Models;

use Database\Factories\TransportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transport extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'plate',
        'description',
        'phone',
        'insurance',
        'usage',
        'observation',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public static function newFactory(): TransportFactory
    {
        return new TransportFactory();
    }
}
