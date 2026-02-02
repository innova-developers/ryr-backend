<?php

namespace App;

use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SatisfactionSurvey extends Model
{
    protected $fillable = [
        'commission_id',
        'customer_id',
        'token',
        'rating',
        'comment',
        'sent_at',
        'responded_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    /**
     * Relación con la comisión
     */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /**
     * Relación con el cliente
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Verificar si la encuesta ha sido respondida
     */
    public function isResponded(): bool
    {
        return $this->responded_at !== null;
    }

    /**
     * Marcar como respondida
     */
    public function markAsResponded(): void
    {
        $this->update([
            'responded_at' => now(),
        ]);
    }

    /**
     * Generar token único para la encuesta
     */
    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (self::where('token', $token)->exists());

        return $token;
    }
}
