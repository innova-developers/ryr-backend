<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Localidad que atiende una sucursal (RC-483).
 *
 * Define el alcance OPERATIVO: qué trabajo puede tomar un cadete de esa sucursal.
 * No tiene nada que ver con la propiedad administrativa de la comisión, que sigue
 * siendo de la sucursal que la cargó.
 */
class BranchLocality extends Model
{
    use HasFactory;

    protected $table = 'branch_localities';

    protected $fillable = [
        'branch_id',
        'locality',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Las localidades se comparan normalizadas: en la base conviven "SAN GENARO",
     * "San Genaro" y "san genaro" cargadas a mano.
     */
    public function setLocalityAttribute($value): void
    {
        $this->attributes['locality'] = mb_strtoupper(trim((string) $value));
    }
}
