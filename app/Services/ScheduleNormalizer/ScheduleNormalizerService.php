<?php

namespace App\Services\ScheduleNormalizer;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para normalizar y parsear horarios comerciales desde strings con formatos libres
 *
 * El servicio intenta primero un parseo determinístico usando regex.
 * Si falla, usa IA como fallback.
 * El resultado se normaliza a un formato estructurado con rangos horarios.
 */
class ScheduleNormalizerService
{
    public function __construct(
        private readonly ?AIClientInterface $aiClient = null
    ) {
    }

    /**
     * Normaliza un horario comercial desde un string libre
     *
     * @param string $rawSchedule Horario en formato libre (ej: "8a12 y 15 a 20", "04:30-07:30")
     * @return array Array normalizado con estructura:
     *   [
     *     'ranges' => [
     *       ['start' => '08:00', 'end' => '12:00', 'confidence' => 'high'],
     *       ['start' => '15:00', 'end' => '20:00', 'confidence' => 'high']
     *     ],
     *     'raw' => $rawSchedule,
     *     'normalized_string' => '8 a 12 y 15 a 20', // String después de preprocesamiento
     *     'normalized_at' => Carbon::now()->toISOString()
     *   ]
     */
    public function normalize(string $rawSchedule): array
    {
        if (empty(trim($rawSchedule))) {
            return [
                'ranges' => [],
                'raw' => $rawSchedule,
                'normalized_string' => $rawSchedule,
                'normalized_at' => Carbon::now()->toISOString(),
            ];
        }

        // Preprocesar el string para normalizarlo
        $normalizedString = $this->preprocessSchedule($rawSchedule);

        // Intentar parseo determinístico primero
        $ranges = $this->parseWithRegex($rawSchedule);

        // Si el parseo determinístico no encontró rangos válidos, usar IA como fallback
        if (empty($ranges) && $this->aiClient !== null) {
            Log::info('Parseo determinístico falló, usando IA como fallback', [
                'raw_schedule' => $rawSchedule,
                'normalized_string' => $normalizedString,
            ]);

            $ranges = $this->parseWithAI($rawSchedule);
        }

        return [
            'ranges' => $ranges,
            'raw' => $rawSchedule,
            'normalized_string' => $normalizedString,
            'normalized_at' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Parsea un horario usando regex y normalización determinística
     *
     * Soporta formatos como:
     * - "8a12 y 15 a 20"
     * - "04:30-07:30"
     * - "8 a 12 / 15 a 20"
     * - "8:00-12:00, 15:00-20:00"
     *
     * @param string $schedule Horario a parsear
     * @return array Array de rangos normalizados con confidence 'high'
     */
    public function parseWithRegex(string $schedule): array
    {
        $ranges = [];
        $normalized = $this->preprocessSchedule($schedule);
        $usedPositions = []; // Trackear posiciones ya usadas para evitar duplicados

        // Patrón para detectar rangos horarios: HH:MM-HH:MM o HH-HH
        // Captura múltiples separadores: y, /, ,, -
        $patterns = [
            // Formato con minutos: "08:30-12:00" o "08:30 a 12:00" (prioridad alta)
            ['pattern' => '/(\d{1,2}):(\d{2})\s*[-a]\s*(\d{1,2}):(\d{2})/i', 'type' => 'with_minutes'],
            // Formato sin minutos: "8-12" o "8 a 12" (solo si no está dentro de un rango con minutos)
            ['pattern' => '/(\d{1,2})\s*[-a]\s*(\d{1,2})(?!\d|:)/', 'type' => 'without_minutes'],
            // Formato con "de" o "hasta": "8 de la mañana a 12"
            ['pattern' => '/(\d{1,2})\s+(?:de\s+)?(?:la\s+)?(?:mañana|tarde|noche)?\s*[-a]\s*(\d{1,2})/i', 'type' => 'descriptive'],
        ];

        foreach ($patterns as $patternData) {
            $pattern = $patternData['pattern'];
            $type = $patternData['type'];

            if (preg_match_all($pattern, $normalized, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($matches as $match) {
                    $startPos = $match[0][1]; // Posición inicial del match
                    $matchLength = strlen($match[0][0]); // Longitud del match

                    // Verificar si esta posición ya fue usada por un patrón más específico
                    $isOverlapping = false;
                    foreach ($usedPositions as $used) {
                        if ($startPos >= $used['start'] && $startPos < $used['end']) {
                            $isOverlapping = true;

                            break;
                        }
                    }

                    if ($isOverlapping) {
                        continue;
                    }

                    // Convertir match con offset a array normal
                    $normalizedMatch = [];
                    foreach ($match as $group) {
                        $normalizedMatch[] = is_array($group) ? $group[0] : $group;
                    }

                    $range = $this->extractRangeFromMatch($normalizedMatch, $pattern);
                    if ($range !== null) {
                        $ranges[] = $range;
                        $usedPositions[] = [
                            'start' => $startPos,
                            'end' => $startPos + $matchLength,
                        ];
                    }
                }
            }
        }

        // Si no encontramos rangos con los patrones principales, intentar buscar horas sueltas
        if (empty($ranges)) {
            $ranges = $this->parseLooseHours($normalized);
        }

        // Eliminar duplicados y ordenar
        $ranges = $this->deduplicateAndSort($ranges);

        return $ranges;
    }

    /**
     * Preprocesa el string de horario para normalizarlo antes del parseo
     *
     * @param string $schedule Horario original
     * @return string Horario normalizado
     */
    private function preprocessSchedule(string $schedule): string
    {
        // Normalizar espacios múltiples
        $normalized = preg_replace('/\s+/', ' ', trim($schedule));

        // Normalizar separadores comunes
        $normalized = str_replace(['/', ','], ' y ', $normalized);

        // Normalizar "hs" o "horas" después de números
        $normalized = preg_replace('/(\d+)\s*(?:hs|horas?)/i', '$1', $normalized);

        // Normalizar "de la noche" a formato 24h (agregar 12 horas)
        $normalized = preg_replace_callback('/(\d{1,2})\s*(?:de\s+)?(?:la\s+)?noche/i', function ($matches) {
            $hour = (int) $matches[1];
            if ($hour >= 1 && $hour <= 12) {
                return ($hour + 12) . ':00';
            }

            return $matches[0];
        }, $normalized);

        // Normalizar "de la tarde" (después de 12) a formato 24h
        $normalized = preg_replace_callback('/(\d{1,2})\s*(?:de\s+)?(?:la\s+)?tarde/i', function ($matches) {
            $hour = (int) $matches[1];
            if ($hour >= 1 && $hour <= 12) {
                return ($hour + 12) . ':00';
            }

            return $matches[0];
        }, $normalized);

        // Normalizar "abre" y "cierra"
        $normalized = preg_replace('/abre\s*(\d+)/i', '$1', $normalized);
        $normalized = preg_replace('/cierra\s*(?:tipo|a|hasta)?\s*(\d+)/i', '$1', $normalized);

        return $normalized;
    }

    /**
     * Extrae un rango horario desde un match de regex
     *
     * @param array $match Match del regex
     * @param string $pattern Patrón usado
     * @return array|null Rango normalizado o null si es inválido
     */
    private function extractRangeFromMatch(array $match, string $pattern): ?array
    {
        // Patrón con minutos: "08:30-12:00"
        if (strpos($pattern, ':(\d{2})') !== false && count($match) >= 5) {
            $startHour = (int) $match[1];
            $startMin = (int) $match[2];
            $endHour = (int) $match[3];
            $endMin = (int) $match[4];

            if ($this->isValidTime($startHour, $startMin) && $this->isValidTime($endHour, $endMin)) {
                return [
                    'start' => sprintf('%02d:%02d', $startHour, $startMin),
                    'end' => sprintf('%02d:%02d', $endHour, $endMin),
                    'confidence' => 'high',
                ];
            }
        }

        // Patrón sin minutos: "8-12"
        if (count($match) >= 3) {
            $startHour = (int) $match[1];
            $endHour = (int) $match[2];

            // Detectar si es formato 24h o 12h
            if ($endHour < $startHour && $endHour <= 12) {
                // Probablemente formato 12h, convertir end a 24h si es PM
                $endHour = $this->normalizeHour($endHour, $startHour);
            }

            if ($this->isValidHour($startHour) && $this->isValidHour($endHour)) {
                return [
                    'start' => sprintf('%02d:00', $startHour),
                    'end' => sprintf('%02d:00', $endHour),
                    'confidence' => 'high',
                ];
            }
        }

        return null;
    }

    /**
     * Parsea horas sueltas cuando no se encuentran rangos claros
     *
     * @param string $schedule Horario normalizado
     * @return array Array de rangos encontrados
     */
    private function parseLooseHours(string $schedule): array
    {
        $ranges = [];
        // Buscar todas las horas mencionadas
        preg_match_all('/(\d{1,2})(?::(\d{2}))?/i', $schedule, $matches, PREG_SET_ORDER);

        $hours = [];
        foreach ($matches as $match) {
            $hour = (int) $match[1];
            $min = isset($match[2]) ? (int) $match[2] : 0;

            if ($this->isValidTime($hour, $min)) {
                $hours[] = ['hour' => $hour, 'min' => $min];
            }
        }

        // Si encontramos horas pares, crear rangos
        if (count($hours) >= 2 && count($hours) % 2 === 0) {
            for ($i = 0; $i < count($hours); $i += 2) {
                $start = $hours[$i];
                $end = $hours[$i + 1];

                $ranges[] = [
                    'start' => sprintf('%02d:%02d', $start['hour'], $start['min']),
                    'end' => sprintf('%02d:%02d', $end['hour'], $end['min']),
                    'confidence' => 'medium',
                ];
            }
        }

        return $ranges;
    }

    /**
     * Normaliza una hora considerando el contexto (12h vs 24h)
     *
     * @param int $hour Hora a normalizar
     * @param int $referenceHour Hora de referencia para contexto
     * @return int Hora normalizada a 24h
     */
    private function normalizeHour(int $hour, int $referenceHour): int
    {
        // Si la hora de referencia es >= 12 y la hora objetivo es <= 12, probablemente es PM
        if ($referenceHour >= 12 && $hour <= 12 && $hour < $referenceHour) {
            return $hour + 12;
        }

        return $hour;
    }

    /**
     * Valida si una hora es válida (0-23)
     *
     * @param int $hour Hora a validar
     * @return bool True si es válida
     */
    private function isValidHour(int $hour): bool
    {
        return $hour >= 0 && $hour <= 23;
    }

    /**
     * Valida si un tiempo (hora:minuto) es válido
     *
     * @param int $hour Hora
     * @param int $min Minuto
     * @return bool True si es válido
     */
    private function isValidTime(int $hour, int $min): bool
    {
        return $this->isValidHour($hour) && $min >= 0 && $min <= 59;
    }

    /**
     * Elimina duplicados y ordena los rangos
     *
     * @param array $ranges Rangos a procesar
     * @return array Rangos únicos y ordenados
     */
    private function deduplicateAndSort(array $ranges): array
    {
        // Eliminar duplicados usando start y end como clave
        $unique = [];
        foreach ($ranges as $range) {
            $key = $range['start'] . '-' . $range['end'];
            if (! isset($unique[$key])) {
                $unique[$key] = $range;
            }
        }

        // Ordenar por hora de inicio
        usort($unique, function ($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        return array_values($unique);
    }

    /**
     * Parsea un horario usando IA como fallback
     *
     * NOTA: Este método NO debe llamarse desde un cronjob.
     * Los horarios deben pre-procesarse antes de ejecutar el cronjob.
     *
     * @param string $schedule Horario a parsear
     * @return array Array de rangos normalizados con confidence 'medium' o 'low'
     */
    public function parseWithAI(string $schedule): array
    {
        if ($this->aiClient === null) {
            Log::warning('Intento de usar IA pero no hay cliente configurado', [
                'schedule' => $schedule,
            ]);

            return [];
        }

        try {
            $result = $this->aiClient->parseSchedule($schedule);

            // Validar y normalizar la respuesta de la IA
            if (! isset($result['ranges']) || ! is_array($result['ranges'])) {
                Log::warning('Respuesta de IA inválida', [
                    'schedule' => $schedule,
                    'result' => $result,
                ]);

                return [];
            }

            $ranges = [];
            foreach ($result['ranges'] as $range) {
                if (isset($range['start']) && isset($range['end'])) {
                    $normalized = $this->normalizeRange($range['start'], $range['end']);
                    if ($normalized !== null) {
                        $ranges[] = [
                            'start' => $normalized['start'],
                            'end' => $normalized['end'],
                            'confidence' => $result['confidence'] ?? 'medium',
                        ];
                    }
                }
            }

            return $ranges;
        } catch (\Exception $e) {
            Log::error('Error al parsear horario con IA', [
                'schedule' => $schedule,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Normaliza un rango horario individual
     *
     * @param string $start Hora de inicio
     * @param string $end Hora de fin
     * @return array|null Rango normalizado o null si es inválido
     */
    private function normalizeRange(string $start, string $end): ?array
    {
        $startTime = $this->parseTimeString($start);
        $endTime = $this->parseTimeString($end);

        if ($startTime === null || $endTime === null) {
            return null;
        }

        return [
            'start' => $startTime,
            'end' => $endTime,
        ];
    }

    /**
     * Parsea un string de tiempo a formato HH:MM
     *
     * @param string $timeString String de tiempo (ej: "8", "8:30", "08:00")
     * @return string|null Tiempo en formato HH:MM o null si es inválido
     */
    private function parseTimeString(string $timeString): ?string
    {
        $timeString = trim($timeString);

        // Formato HH:MM
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeString, $matches)) {
            $hour = (int) $matches[1];
            $min = (int) $matches[2];

            if ($this->isValidTime($hour, $min)) {
                return sprintf('%02d:%02d', $hour, $min);
            }
        }

        // Formato solo hora (asumir :00)
        if (preg_match('/^(\d{1,2})$/', $timeString, $matches)) {
            $hour = (int) $matches[1];

            if ($this->isValidHour($hour)) {
                return sprintf('%02d:00', $hour);
            }
        }

        return null;
    }

    /**
     * Verifica si algún rango cierra en los próximos minutos
     *
     * @param array $ranges Rangos horarios normalizados
     * @param int $minutes Minutos de anticipación (default: 15)
     * @return bool True si algún rango cierra pronto
     */
    public function closesSoon(array $ranges, int $minutes = 15): bool
    {
        if (empty($ranges)) {
            return false;
        }

        $now = Carbon::now();
        $threshold = $now->copy()->addMinutes($minutes);

        foreach ($ranges as $range) {
            // Obtener hora de cierre de hoy
            $endTimeToday = $this->parseTimeToCarbon($range['end'], $now);

            if ($endTimeToday === null) {
                continue;
            }

            // Si el cierre de hoy está dentro del threshold, retornar true
            if ($endTimeToday->isAfter($now) && $endTimeToday->lte($threshold)) {
                return true;
            }

            // Si el cierre de hoy ya pasó, verificar el cierre de mañana
            // Solo si el threshold cruza medianoche
            if ($endTimeToday->isBefore($now) && $threshold->isTomorrow()) {
                $endTimeTomorrow = $endTimeToday->copy()->addDay();

                // Si el cierre de mañana está dentro del threshold, retornar true
                if ($endTimeTomorrow->lte($threshold)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convierte un string de tiempo (HH:MM) a Carbon usando una fecha de referencia
     *
     * @param string $timeString Tiempo en formato HH:MM
     * @param Carbon $reference Fecha de referencia
     * @return Carbon|null Objeto Carbon o null si es inválido
     */
    private function parseTimeToCarbon(string $timeString, Carbon $reference): ?Carbon
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', $timeString, $matches)) {
            return null;
        }

        $hour = (int) $matches[1];
        $min = (int) $matches[2];

        if (! $this->isValidTime($hour, $min)) {
            return null;
        }

        $time = $reference->copy()->setTime($hour, $min, 0);

        // Si la hora es anterior a la hora actual, asumir que es mañana
        if ($time->isBefore($reference)) {
            $time->addDay();
        }

        return $time;
    }
}
