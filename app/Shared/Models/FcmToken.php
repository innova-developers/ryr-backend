<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FcmToken extends Model
{
    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<\App\Shared\Models\User, \App\Shared\Models\FcmToken>
     */
    public function user(): BelongsTo
    {
        /** @var BelongsTo<\App\Shared\Models\User, \App\Shared\Models\FcmToken> $relation */
        $relation = $this->belongsTo(User::class);

        return $relation;
    }

    /**
     * Marcar el token como usado
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Desactivar el token
     */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    /**
     * Activar el token
     */
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }
}
