<?php

namespace App\Console\Commands;

use App\Services\NominatimService;
use App\Shared\Models\Location;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill manual de coordenadas de locaciones con Nominatim.
 *
 * RC-549: GET /cadete/deliveries ya no geocodifica al vuelo. Las locaciones nuevas o editadas
 * se geocodifican solas al guardarse (GeocodeLocationJob); este comando completa las que
 * quedaron sin coordenadas (9.224 de 13.245 en la copia de prod del 23/09).
 *
 * Respeta la política de Nominatim: 1 request por segundo (lo aplica NominatimService, que
 * además no repite direcciones ya resueltas o sin resultado) y User-Agent propio. Si Nominatim
 * responde 429 el servicio se pausa una hora y el comando se corta: volver a correrlo después.
 *
 * Primero van las locaciones usadas por las comisiones más recientes (en la copia del 23/09,
 * 302 de las que no tienen coordenadas se usaron en los últimos 30 días).
 *
 * --limit cuenta sólo las locaciones que hay que consultar a Nominatim. Las que ya tienen
 * resultado en cache se guardan sin consultar, y las que Nominatim ya respondió vacías (cache de
 * 7 días) se saltean; ninguna de las dos cuenta. Si contaran, cada corrida volvería a arrancar
 * por las mismas sin resultado y nunca llegaría a las siguientes: en prod, de las respuestas que
 * traen datos, ~97% vienen vacías (20% de las llamadas contra 0,5% con coordenadas).
 *
 * Correr primero en seco:
 *   php artisan locations:geocode --dry-run
 *   php artisan locations:geocode --limit=300
 */
class GeocodeLocations extends Command
{
    protected $signature = 'locations:geocode
        {--limit=100 : Máximo de locaciones a consultar a Nominatim (0 = todas). Las que ya tienen resultado en cache no cuentan}
        {--dry-run : Lista lo que haría con cada locación, sin llamar a Nominatim ni escribir}';

    protected $description = 'Completa latitud/longitud de las locaciones sin coordenadas usando Nominatim (RC-549)';

    /** Locaciones que se cargan por tanda (el orden lo da la lista de ids). */
    private const CHUNK = 200;

    private const ACTION_QUERY = 'consultar a Nominatim';

    private const ACTION_CACHE = 'guardar desde cache';

    private const ACTION_SKIP = 'saltear: sin resultado conocido';

    public function handle(NominatimService $nominatim): int
    {
        $limit = $this->option('limit');
        if (! is_numeric($limit) || (int) $limit < 0) {
            $this->error('--limit tiene que ser un número >= 0.');

            return self::INVALID;
        }
        $limit = (int) $limit;
        $dryRun = (bool) $this->option('dry-run');

        // Sólo los ids, en orden de prioridad: se leen antes de empezar a guardar para que las
        // locaciones que se completan en el camino no corran el orden (con 9.224 pendientes son
        // unos pocos KB; los modelos se cargan de a CHUNK).
        $lastCommissionById = $this->pendingLocations()
            ->pluck('location_usage.last_commission_id', 'locations.id');
        $pending = $lastCommissionById->count();

        $this->info("Locaciones sin coordenadas: {$pending}.");

        if ($pending === 0) {
            if ($dryRun) {
                $this->line('Modo --dry-run: no se llamó a Nominatim ni se escribió nada.');
            }

            return self::SUCCESS;
        }

        if (! $dryRun && $nominatim->isCircuitOpen()) {
            $this->warn($this->pauseMessage($nominatim) . ' Volvé a correrlo después.');

            return self::FAILURE;
        }

        if (! $dryRun) {
            $this->line('Se consultan hasta ' . ($limit > 0 ? $limit : $pending) . ' a Nominatim, a 1 request por segundo como máximo (hasta 2 por locación).');
        }

        $queried = 0;
        $found = 0;
        $notFound = 0;
        $fromCache = 0;
        $skipped = 0;
        $rows = [];
        $interrupted = false;

        foreach ($lastCommissionById->keys()->chunk(self::CHUNK) as $ids) {
            $locations = Location::query()
                ->whereIn('id', $ids->all())
                ->where(fn (Builder $q) => $q->whereNull('latitude')->orWhereNull('longitude'))
                ->get()
                ->keyBy('id');

            foreach ($ids as $id) {
                if ($limit > 0 && $queried >= $limit) {
                    break 2;
                }

                $location = $locations->get($id);
                if (! $location) {
                    continue; // Se completó mientras tanto (por ejemplo, la editaron en el admin).
                }

                $address = $location->address ?? '';
                $label = "#{$location->id} {$location->address}, {$location->origin}";

                // Ya resuelta en cache (dirección repetida, o geocodificada antes): se guarda sin llamar.
                $cached = $nominatim->getCachedCoordinates($address, $location->origin);
                if ($cached) {
                    $fromCache++;
                    if ($dryRun) {
                        $rows[] = $this->row($location, $lastCommissionById->get($id), self::ACTION_CACHE);
                    } else {
                        $this->saveCoordinates($location, $cached);
                        $this->line("  {$label} → {$cached['latitude']}, {$cached['longitude']} (desde cache)");
                    }

                    continue;
                }

                // Nominatim ya dijo que no la encuentra: no se pregunta ni se cuenta en --limit.
                if ($nominatim->isKnownMiss($address, $location->origin)) {
                    $skipped++;
                    if ($dryRun && $this->output->isVerbose()) {
                        $rows[] = $this->row($location, $lastCommissionById->get($id), self::ACTION_SKIP);
                    } elseif (! $dryRun && $this->output->isVerbose()) {
                        $this->line("  {$label} → salteada (sin resultado conocido)");
                    }

                    continue;
                }

                if ($dryRun) {
                    $queried++;
                    $rows[] = $this->row($location, $lastCommissionById->get($id), self::ACTION_QUERY);

                    continue;
                }

                if ($nominatim->isCircuitOpen()) {
                    $interrupted = true;

                    break 2;
                }

                $coordinates = $nominatim->getCoordinates($address, $location->origin);

                if ($coordinates) {
                    $queried++;
                    $found++;
                    $this->saveCoordinates($location, $coordinates);
                    $this->line("  {$label} → {$coordinates['latitude']}, {$coordinates['longitude']}");
                } elseif (! $nominatim->isCircuitOpen()) {
                    $queried++;
                    $notFound++;
                    $this->line("  {$label} → sin resultado");
                } else {
                    // No se cuenta: el 429 la cortó en el medio y queda para la próxima corrida.
                    $interrupted = true;

                    break 2;
                }
            }
        }

        if ($dryRun) {
            $this->table(['ID', 'Nombre', 'Dirección', 'Ciudad', 'Última comisión', 'Acción'], $rows);
            $this->line("Modo --dry-run: se consultarían {$queried} y se guardarían {$fromCache} desde cache. No se llamó a Nominatim ni se escribió nada.");
            if ($skipped > 0) {
                $this->line("Se saltearían {$skipped} con dirección sin resultado conocido (Nominatim ya respondió vacío; se reintentan cuando vence la cache de 7 días)" . ($this->output->isVerbose() ? '.' : '. Con -v se listan.'));
            }
            if ($nominatim->isCircuitOpen()) {
                $this->warn($this->pauseMessage($nominatim) . ' Una corrida real ahora no arrancaría.');
            }

            return self::SUCCESS;
        }

        $this->info("Consultadas {$queried}: {$found} con coordenadas, {$notFound} sin resultado. Guardadas desde cache: {$fromCache}. Salteadas por sin resultado conocido: {$skipped}. Requests a Nominatim: {$nominatim->requestCount()}.");

        if ($interrupted || $nominatim->isCircuitOpen()) {
            $this->warn($this->pauseMessage($nominatim) . ' Se cortó el proceso; volvé a correrlo después.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function saveCoordinates(Location $location, array $coordinates): void
    {
        $location->forceFill([
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
        ])->saveQuietly();
    }

    private function row(Location $location, $lastCommissionId, string $action): array
    {
        return [
            $location->id,
            $location->name,
            $location->address,
            $location->origin,
            $lastCommissionId ?? '-',
            $action,
        ];
    }

    /**
     * La pausa puede venir de un 429/403 (1 h) o de un timeout/5xx (5 min): se informa el motivo real.
     */
    private function pauseMessage(NominatimService $nominatim): string
    {
        $until = $nominatim->circuitOpenUntil();
        $reason = $nominatim->circuitReason();

        return 'Nominatim está en pausa'
            . ($until ? ' hasta las ' . $until->format('H:i') : '')
            . ($reason ? " ({$reason})" : '')
            . '.';
    }

    /**
     * Locaciones sin coordenadas, primero las de las comisiones más recientes.
     */
    private function pendingLocations(): Builder
    {
        // Última comisión que usa cada locación como origen o destino. Dos GROUP BY que usan
        // los índices de las FK, en vez de una subconsulta con OR por cada locación.
        $usage = DB::query()
            ->fromSub(
                DB::table('commissions')
                    ->selectRaw('origin_location_id as location_id, max(id) as last_id')
                    ->whereNotNull('origin_location_id')
                    ->groupBy('origin_location_id')
                    ->unionAll(
                        DB::table('commissions')
                            ->selectRaw('destination_location_id as location_id, max(id) as last_id')
                            ->whereNotNull('destination_location_id')
                            ->groupBy('destination_location_id')
                    ),
                'usage_by_column'
            )
            ->selectRaw('location_id, max(last_id) as last_commission_id')
            ->groupBy('location_id');

        return Location::query()
            ->leftJoinSub($usage, 'location_usage', 'location_usage.location_id', '=', 'locations.id')
            ->where(fn (Builder $q) => $q->whereNull('locations.latitude')->orWhereNull('locations.longitude'))
            ->orderByDesc('location_usage.last_commission_id')
            ->orderByDesc('locations.id');
    }
}
