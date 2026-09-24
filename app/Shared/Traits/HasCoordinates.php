<?php

namespace App\Shared\Traits;

use App\Jobs\GeocodeLocationJob;

trait HasCoordinates
{
    /**
     * Boot the trait and set up event listeners
     *
     * RC-549: antes se geocodificaba en `saving`, síncrono: el alta o edición de una locación
     * esperaba a Nominatim (hasta 2 llamadas con timeout de 10 s, y en prod el 99,5% fallaba).
     * Ahora se guarda primero y la geocodificación corre después de enviar la respuesta.
     */
    protected static function bootHasCoordinates()
    {
        static::saved(function ($model) {
            $model->queueGeocodingIfNeeded();
        });
    }

    /**
     * Programa la geocodificación si cambió la dirección o la ciudad.
     *
     * Se llama desde `saved`, antes de que Eloquent sincronice el original, así que isDirty()
     * todavía refleja lo que cambió en este guardado (igual que el `saving` de antes).
     */
    public function queueGeocodingIfNeeded(): void
    {
        if (! config('services.nominatim.geocode_on_save', true)) {
            return;
        }

        if (! $this->isDirty(['address', 'origin'])) {
            return;
        }

        // Si quien guarda ya trae las coordenadas (seeders, backfill), no se pisan.
        if ($this->isDirty(['latitude', 'longitude']) && $this->hasCoordinates()) {
            return;
        }

        GeocodeLocationJob::dispatchAfterResponse($this->getKey());
    }

    /**
     * Get coordinates array
     */
    public function getCoordinates(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ];
        }

        return null;
    }

    /**
     * Check if coordinates are available
     */
    public function hasCoordinates(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }
}
