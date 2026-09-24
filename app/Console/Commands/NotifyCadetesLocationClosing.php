<?php

namespace App\Console\Commands;

use App\Services\FcmNotificationService;
use App\Services\ScheduleNormalizer\ScheduleNormalizerService;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Commission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Comando para notificar a cadetes cuando un comercio está por cerrar
 *
 * Este comando:
 * 1. Busca comisiones activas (CADETE_ASIGNADO hasta EN_PROCESO_ENTREGA)
 * 2. Obtiene horarios de origin_location y destination_location
 * 3. Normaliza los horarios usando ScheduleNormalizerService
 * 4. Verifica si alguna location está por cerrar en los próximos 30 minutos (configurable)
 * 5. Envía notificación push FCM al cadete asignado
 *
 * Ejecutar cada 5-10 minutos:
 * * * * * * php artisan cadetes:notify-location-closing
 */
class NotifyCadetesLocationClosing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cadetes:notify-location-closing {--minutes=30 : Minutos de anticipación para notificar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notifica a cadetes cuando un comercio está por cerrar';

    public function __construct(
        private readonly ScheduleNormalizerService $scheduleNormalizer,
        private readonly FcmNotificationService $fcmService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $this->info("Buscando comisiones activas y verificando cierres en los próximos {$minutes} minutos...");

        // Estados de comisiones activas (líneas 10-17 del enum)
        $activeStatuses = [
            CommissionStatus::CADETE_ASIGNADO,
            CommissionStatus::CADETE_EN_CAMINO_ORIGEN,
            CommissionStatus::EN_PUNTO_RETIRO,
            CommissionStatus::ENCOMIENDA_RETIRADA,
            CommissionStatus::EN_CAMINO_PLANTA,
            CommissionStatus::EN_TRANSITO_DESTINO,
            CommissionStatus::EN_SUCURSAL_DESTINO,
            CommissionStatus::EN_PROCESO_ENTREGA,
        ];

        // Buscar comisiones activas con cadete asignado y locations cargadas
        $commissions = Commission::whereIn('status', $activeStatuses)
            ->whereNotNull('cadete_id')
            ->with(['originLocation', 'destinationLocation', 'cadete'])
            ->get();

        $this->info("Encontradas {$commissions->count()} comisiones activas");

        $notificationsSent = 0;
        $notificationsSkipped = 0;

        foreach ($commissions as $commission) {
            try {
                $this->line("Procesando comisión #{$commission->id}...");

                $originClosesSoon = false;
                $destinationClosesSoon = false;
                $originMinutos = null;
                $destinationMinutos = null;
                $originName = null;
                $destinationName = null;

                // Verificar horario de origen
                if ($commission->originLocation && ! empty($commission->originLocation->schedule)) {
                    $this->line("  Verificando horario de origen: {$commission->originLocation->schedule}");
                    $normalizedOrigin = $this->scheduleNormalizer->normalize($commission->originLocation->schedule);

                    if (isset($normalizedOrigin['normalized_string'])) {
                        $this->line("  String normalizado (origen): {$normalizedOrigin['normalized_string']}");
                    }

                    if (! empty($normalizedOrigin['ranges'])) {
                        $originMinutos = $this->scheduleNormalizer->minutesUntilClose($normalizedOrigin['ranges'], $minutes);
                        $originClosesSoon = $originMinutos !== null;

                        if ($originClosesSoon) {
                            $originName = $commission->originLocation->name;
                            $this->line("  ✓ Origen está por cerrar");
                        } else {
                            $this->line("  - Origen no está por cerrar");
                        }
                    } else {
                        $this->line("  - No se pudieron parsear rangos del origen");
                    }
                } else {
                    $this->line("  - Origen sin horario");
                }

                // Verificar horario de destino (siempre verificar, independientemente del origen)
                if ($commission->destinationLocation && ! empty($commission->destinationLocation->schedule)) {
                    $this->line("  Verificando horario de destino: {$commission->destinationLocation->schedule}");
                    $normalizedDestination = $this->scheduleNormalizer->normalize($commission->destinationLocation->schedule);

                    if (isset($normalizedDestination['normalized_string'])) {
                        $this->line("  String normalizado (destino): {$normalizedDestination['normalized_string']}");
                    }

                    if (! empty($normalizedDestination['ranges'])) {
                        $destinationMinutos = $this->scheduleNormalizer->minutesUntilClose($normalizedDestination['ranges'], $minutes);
                        $destinationClosesSoon = $destinationMinutos !== null;

                        if ($destinationClosesSoon) {
                            $destinationName = $commission->destinationLocation->name;
                            $this->line("  ✓ Destino está por cerrar");
                        } else {
                            $this->line("  - Destino no está por cerrar");
                        }
                    } else {
                        $this->line("  - No se pudieron parsear rangos del destino");
                    }
                } else {
                    $this->line("  - Destino sin horario");
                }

                // Enviar notificaciones si es necesario
                if (! $commission->cadete) {
                    $this->line("  - Comisión sin cadete asignado");
                    $notificationsSkipped++;

                    continue;
                }

                $hasNotifications = false;

                // Notificar sobre origen si está por cerrar
                if ($originClosesSoon && $originName) {
                    if ($this->sendNotification($commission, $originName, 'origen', $originMinutos ?? $minutes)) {
                        $notificationsSent++;
                    }
                    $hasNotifications = true;
                }

                // Notificar sobre destino si está por cerrar
                if ($destinationClosesSoon && $destinationName) {
                    if ($this->sendNotification($commission, $destinationName, 'destino', $destinationMinutos ?? $minutes)) {
                        $notificationsSent++;
                    }
                    $hasNotifications = true;
                }

                if (! $hasNotifications) {
                    $this->line("  - No se requiere notificación");
                    $notificationsSkipped++;
                }
            } catch (\Exception $e) {
                Log::error('Error al procesar comisión en notificación de cierre', [
                    'commission_id' => $commission->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->warn("Error procesando comisión #{$commission->id}: {$e->getMessage()}");
                $notificationsSkipped++;
            }
        }

        $this->info("Proceso completado:");
        $this->info("  - Notificaciones enviadas: {$notificationsSent}");
        $this->info("  - Comisiones sin notificación: {$notificationsSkipped}");

        return Command::SUCCESS;
    }

    /**
     * Envía notificación push al cadete sobre el cierre inminente
     *
     * @param Commission $commission Comisión relacionada
     * @param string $locationName Nombre de la location que está por cerrar
     * @param string $locationType Tipo de location ('origen' o 'destino')
     * @param int $minutes Minutos hasta el cierre
     */
    private function sendNotification(
        Commission $commission,
        string $locationName,
        string $locationType,
        int $minutes
    ): bool {
        try {
            $cadeteId = $commission->cadete_id;

            if (! $cadeteId) {
                return false;
            }

            // El comando corre cada 5 minutos con una ventana de 30: sin esto, cada comercio
            // que cierra le llegaba al cadete unas 6 veces seguidas. Se avisa una vez por día
            // por comisión y lugar, y se marca sólo si el push salió, así un fallo de FCM se
            // reintenta en la corrida siguiente.
            $locationId = $locationType === 'origen' ? $commission->origin_location_id : $commission->destination_location_id;
            $claveAviso = "aviso-cierre:{$commission->id}:{$locationType}:{$locationId}:" . now()->toDateString();

            if (Cache::has($claveAviso)) {
                $this->line("  - Ya se avisó hoy del cierre de {$locationName} ({$locationType})");

                return false;
            }

            $locationTypeLabel = $locationType === 'origen' ? 'origen' : 'destino';
            $commissionInfo = "Comisión #{$commission->id}";

            $payload = [
                'title' => '⚠️ Comercio por cerrar',
                'body' => "El {$locationTypeLabel} '{$locationName}' de la {$commissionInfo} cerrará en aproximadamente {$minutes} minutos",
                'data' => [
                    'type' => 'location_closing_soon',
                    'commission_id' => $commission->id,
                    'location_name' => $locationName,
                    'location_type' => $locationType,
                    'minutes_until_close' => $minutes,
                    'location_id' => $locationType === 'origen'
                        ? $commission->origin_location_id
                        : $commission->destination_location_id,
                ],
            ];

            $result = $this->fcmService->sendPushToUser($cadeteId, $payload);

            if ($result['sent'] > 0) {
                Cache::put($claveAviso, true, now()->endOfDay());

                Log::info('Notificación de cierre enviada a cadete', [
                    'cadete_id' => $cadeteId,
                    'commission_id' => $commission->id,
                    'location_name' => $locationName,
                    'location_type' => $locationType,
                    'sent' => $result['sent'],
                ]);

                $this->line("  ✓ Notificación enviada a cadete #{$cadeteId} - {$locationName} ({$locationType})");
            } else {
                Log::warning('No se pudo enviar notificación de cierre a cadete', [
                    'cadete_id' => $cadeteId,
                    'commission_id' => $commission->id,
                    'result' => $result,
                ]);

                $this->warn("  ✗ No se pudo enviar notificación a cadete #{$cadeteId}");
            }

            return $result['sent'] > 0;
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación de cierre', [
                'cadete_id' => $commission->cadete_id,
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);

            $this->error("  ✗ Error enviando notificación: {$e->getMessage()}");

            return false;
        }
    }
}
