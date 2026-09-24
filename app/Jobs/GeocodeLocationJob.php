<?php

namespace App\Jobs;

use App\Services\NominatimService;
use App\Shared\Models\Location;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Geocodifica una locación con Nominatim y guarda latitud/longitud.
 *
 * RC-549: antes esto corría dentro del `saving` del modelo, así que el alta o edición de una
 * locación (admin, app de cadetes o alta de cliente) esperaba a Nominatim: hasta 2 llamadas de
 * 10 s de timeout. Como en prod la cola es `sync` y no hay cron, se despacha con
 * dispatchAfterResponse(): corre en el terminate del mismo proceso, después de enviar la
 * respuesta (Symfony llama a litespeed_finish_request() bajo mod_lsapi), así que el usuario no
 * lo espera. Si algún día hay un worker, basta con implementar ShouldQueue.
 */
class GeocodeLocationJob
{
    use Dispatchable;

    public function __construct(public readonly int $locationId)
    {
    }

    public function handle(NominatimService $nominatim): void
    {
        $location = Location::find($this->locationId);
        if (! $location) {
            return;
        }

        try {
            $coordinates = $nominatim->getCoordinates($location->address ?? '', $location->origin);
        } catch (\Throwable $e) {
            Log::warning('No se pudo geocodificar la locación', [
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Sin resultado (o Nominatim en pausa): el servicio ya dejó su única línea de log.
        if (! $coordinates) {
            return;
        }

        // saveQuietly: no vuelve a disparar el hook del modelo.
        $location->forceFill([
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ])->saveQuietly();

        Log::info('Coordinates calculated for location', [
            'location_id' => $location->id,
            'address' => $location->address,
            'origin' => $location->origin,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ]);
    }
}
