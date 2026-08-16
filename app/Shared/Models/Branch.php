<?php

namespace App\Shared\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Database\Factories\BranchFactory>
 */
class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'name', 'address', 'schedule', 'phone', 'secondary_phone', 'franchise_id'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Shared\Models\User, \App\Shared\Models\Branch>
     */
    public function users(): HasMany
    {
        /** @var \Illuminate\Database\Eloquent\Relations\HasMany<\App\Shared\Models\User, \App\Shared\Models\Branch> $relation */
        $relation = $this->hasMany(User::class);

        return $relation;
    }

    /**
     * Localidades que atiende esta sucursal (RC-483). Define qué comisiones de otras
     * sucursales puede tomar su gente, sin cambiar de quién es la comisión.
     */
    public function localities(): HasMany
    {
        return $this->hasMany(BranchLocality::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }
}
