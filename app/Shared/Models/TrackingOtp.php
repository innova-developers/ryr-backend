<?php

namespace App\Shared\Models;

use App\Services\TrackingOtpService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de un solo uso para ver el detalle de un envío (RC-500).
 *
 * Se guarda el hash del código, nunca el código en claro.
 */
class TrackingOtp extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_id',
        'code_hash',
        'sent_to_masked',
        'attempts',
        'expires_at',
        'used_at',
        'request_ip',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->expires_at->isFuture()
            && $this->attempts < TrackingOtpService::MAX_INTENTOS;
    }
}
