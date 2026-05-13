<?php

namespace App;

use App\Shared\Models\Commission;
use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliverySignature extends Model
{
    protected $fillable = [
        'commission_id',
        'cadete_id',
        'receiver_name',
        'receiver_phone',
        'notes',
        'signature_image',
        'delivery_timestamp',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'delivery_timestamp' => 'datetime',
    ];

    /**
     * Relación con la comisión
     */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    /**
     * Relación con el cadete que registró la firma
     */
    public function cadete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadete_id');
    }

    /**
     * Obtener la firma como imagen base64
     */
    public function getSignatureImageAttribute($value): ?string
    {
        return $value;
    }

    /**
     * Obtener la URL de la firma (si se guarda como archivo)
     */
    public function getSignatureUrlAttribute(): string
    {
        // Si la imagen está guardada como base64, devolverla directamente
        if (str_starts_with($this->signature_image, 'data:image')) {
            return $this->signature_image;
        }

        // Si se guarda como archivo, devolver la URL
        return asset('storage/signatures/' . $this->signature_image);
    }

    /**
     * Verificar si la firma es válida (base64 PNG)
     */
    public function isValidSignature(): bool
    {
        if (empty($this->signature_image)) {
            return false;
        }

        // Verificar que sea base64 válido
        if (! base64_decode($this->signature_image, true)) {
            return false;
        }

        // Verificar que sea una imagen PNG
        $imageData = base64_decode($this->signature_image);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_buffer($finfo, $imageData);
        finfo_close($finfo);

        return $mimeType === 'image/png';
    }

    /**
     * Obtener el tamaño de la imagen de firma
     */
    public function getSignatureSize(): int
    {
        if (empty($this->signature_image)) {
            return 0;
        }

        $imageData = base64_decode($this->signature_image);

        return strlen($imageData);
    }
}
