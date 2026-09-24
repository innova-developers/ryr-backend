<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

class NominatimService
{
    private string $baseUrl = 'https://nominatim.openstreetmap.org/search';

    /*
     * RC-549: en prod este servicio se llamaba síncrono desde GET /cadete/deliveries por cada
     * ubicación sin coordenadas: ~12.000 llamadas por día hábil, 99,5% fallidas (78% HTTP 429,
     * 20% sin resultados) y el ~85% del laravel.log, porque sólo se cacheaba el éxito. La misma
     * dirección llegó a pedirse 1.259 veces en 2 días. Ahora también se cachea el fracaso y un
     * 429 corta todas las llamadas durante una hora.
     */
    private const SUCCESS_TTL_DAYS = 30;

    private const MISS_TTL_DAYS = 7;

    private const CIRCUIT_KEY = 'geocode_nominatim_circuit_open_until';

    /** Motivo de la pausa ("respondió HTTP 429", "no respondió"), para informarlo en el comando. */
    private const CIRCUIT_REASON_KEY = 'geocode_nominatim_circuit_reason';

    /** 429/403: Nominatim nos está limitando o bloqueando. Reintentar antes sólo lo empeora. */
    private const RATE_LIMITED_PAUSE_SECONDS = 3600;

    /** Timeout o 5xx: Nominatim no responde; pausa corta. */
    private const UNAVAILABLE_PAUSE_SECONDS = 300;

    private const LAST_REQUEST_KEY = 'geocode_nominatim_last_request_at';

    /** Política de uso de Nominatim: como máximo 1 request por segundo para toda la app. */
    private const MIN_SECONDS_BETWEEN_REQUESTS = 1.0;

    /**
     * Antes era 10 s: hubo 18 timeouts de 10 s en septiembre, cada uno con el request del
     * cadete bloqueado. Una respuesta normal de Nominatim tarda 0,1-0,3 s.
     */
    private const TIMEOUT_SECONDS = 5;

    private const CONNECT_TIMEOUT_SECONDS = 3;

    /** Requests HTTP hechos por esta instancia (lo usa el comando de backfill para informar). */
    private int $requestCount = 0;

    /**
     * Obtiene las coordenadas de una dirección usando Nominatim (OpenStreetMap)
     *
     * IMPORTANTE: Nominatim requiere un User-Agent identificando tu aplicación.
     * Sin esto, pueden bloquear tus requests.
     *
     * Puede hacer hasta 2 requests HTTP (dirección completa y simplificada), así que no se
     * debe llamar dentro de un request de usuario: se usa desde GeocodeLocationJob (después
     * de responder) y desde el comando locations:geocode.
     */
    public function getCoordinates(string $address, ?string $city = null): ?array
    {
        foreach ($this->candidateAddresses($address, $city) as $candidate) {
            $cached = Cache::get($this->successKey($candidate));
            if ($cached) {
                return $cached;
            }

            // Dirección que Nominatim ya dijo no conocer: no se vuelve a preguntar por 7 días.
            if (Cache::has($this->missKey($candidate))) {
                continue;
            }

            // Circuito abierto: no se llama ni se loguea (antes era una línea por llamada).
            if ($this->isCircuitOpen()) {
                return null;
            }

            $coordinates = $this->searchNominatim($candidate);
            if ($coordinates) {
                return $coordinates;
            }
        }

        return null;
    }

    /**
     * Coordenadas ya conocidas para la dirección, SIN llamar a Nominatim.
     *
     * Lee sólo la cache de éxitos (la misma que llenaba el geocoding al vuelo), así que es
     * seguro llamarlo dentro de un GET.
     */
    public function getCachedCoordinates(string $address, ?string $city = null): ?array
    {
        foreach ($this->candidateAddresses($address, $city) as $candidate) {
            $cached = Cache::get($this->successKey($candidate));
            if ($cached) {
                return $cached;
            }
        }

        return null;
    }

    /**
     * true si Nominatim ya respondió "sin resultados" para todas las variantes de la dirección
     * (cache de 7 días) y no hay ninguna con coordenadas: getCoordinates() devolvería null sin
     * salir a la red. Lo usa el backfill para no gastar su --limit en direcciones ya descartadas.
     */
    public function isKnownMiss(string $address, ?string $city = null): bool
    {
        foreach ($this->candidateAddresses($address, $city) as $candidate) {
            if (Cache::has($this->successKey($candidate)) || ! Cache::has($this->missKey($candidate))) {
                return false;
            }
        }

        return true;
    }

    /**
     * true mientras Nominatim esté en pausa por un 429/403 (1 h) o por timeout/5xx (5 min).
     */
    public function isCircuitOpen(): bool
    {
        return (int) Cache::get(self::CIRCUIT_KEY, 0) > now()->getTimestamp();
    }

    /**
     * Momento hasta el que Nominatim está en pausa, o null si no lo está.
     */
    public function circuitOpenUntil(): ?Carbon
    {
        $until = (int) Cache::get(self::CIRCUIT_KEY, 0);

        return $until > now()->getTimestamp() ? now()->setTimestamp($until) : null;
    }

    /**
     * Por qué se pausó Nominatim ("respondió HTTP 429", "no respondió"...), o null si no está en pausa.
     */
    public function circuitReason(): ?string
    {
        return $this->isCircuitOpen() ? Cache::get(self::CIRCUIT_REASON_KEY) : null;
    }

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    /**
     * Direcciones a probar, en orden: la completa normalizada y, si difiere, la simplificada.
     *
     * @return string[]
     */
    private function candidateAddresses(string $address, ?string $city): array
    {
        $fullAddress = $this->buildFullAddress($address, $city);
        $candidates = [$fullAddress];

        if ($address) {
            $simplifiedAddress = $this->simplifyAddress($address, $city);
            if ($simplifiedAddress !== $fullAddress) {
                $candidates[] = $simplifiedAddress;
            }
        }

        return $candidates;
    }

    /**
     * Misma clave que usaba el servicio antes de RC-549: la cache de éxitos que ya existe en
     * prod se sigue leyendo.
     */
    private function successKey(string $fullAddress): string
    {
        return 'geocode_nominatim_' . md5($fullAddress);
    }

    private function missKey(string $fullAddress): string
    {
        return 'geocode_nominatim_miss_' . md5($fullAddress);
    }

    /**
     * Realiza la búsqueda en Nominatim
     */
    private function searchNominatim(string $fullAddress): ?array
    {
        $this->waitForRateLimit();
        $this->requestCount++;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent' => $this->userAgent(),
                    'Accept-Language' => 'es-AR,es,en',
                ])
                ->get($this->baseUrl, [
                    'q' => $fullAddress,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'countrycodes' => 'ar', // Argentina
                ]);
        } catch (\Exception $e) {
            $this->openCircuit(self::UNAVAILABLE_PAUSE_SECONDS, 'no respondió', [
                'address' => $fullAddress,
                'error' => Str::limit($e->getMessage(), 200),
            ]);

            return null;
        }

        $status = $response->status();

        if (in_array($status, [403, 429], true)) {
            // Una sola línea, sin el HTML de la página de error (antes se guardaba entero en
            // cada 429: ~540 bytes por llamada, más un warning por ubicación desde el controller).
            $this->openCircuit(self::RATE_LIMITED_PAUSE_SECONDS, "respondió HTTP {$status}", [
                'address' => $fullAddress,
            ]);

            return null;
        }

        if ($response->serverError()) {
            $this->openCircuit(self::UNAVAILABLE_PAUSE_SECONDS, "respondió HTTP {$status}", [
                'address' => $fullAddress,
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Error en request a Nominatim', [
                'status' => $status,
                'address' => $fullAddress,
                'body_bytes' => strlen($response->body()),
            ]);

            return null;
        }

        $data = $response->json();

        if (is_array($data) && ! empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            $coordinates = [
                'latitude' => (float) $data[0]['lat'],
                'longitude' => (float) $data[0]['lon'],
            ];

            // Cachear por 30 días (Nominatim permite cachear resultados)
            Cache::put($this->successKey($fullAddress), $coordinates, now()->addDays(self::SUCCESS_TTL_DAYS));

            Log::info('Coordenadas obtenidas de Nominatim', [
                'address' => $fullAddress,
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
            ]);

            return $coordinates;
        }

        if (is_array($data)) {
            // Sin resultados (el 20% de las llamadas en prod): no se vuelve a preguntar por 7 días.
            Cache::put($this->missKey($fullAddress), true, now()->addDays(self::MISS_TTL_DAYS));

            Log::warning('Nominatim no encontró resultados', [
                'address' => $fullAddress,
                'retry_after_days' => self::MISS_TTL_DAYS,
            ]);

            return null;
        }

        Log::warning('Respuesta inesperada de Nominatim', [
            'status' => $status,
            'address' => $fullAddress,
            'body_bytes' => strlen($response->body()),
        ]);

        return null;
    }

    /**
     * Abre el circuito: durante $seconds nadie en la app llama a Nominatim. Se loguea una sola
     * vez, al abrirlo; mientras está abierto las consultas devuelven null en silencio.
     */
    private function openCircuit(int $seconds, string $reason, array $context): void
    {
        $until = now()->addSeconds($seconds);
        Cache::put(self::CIRCUIT_KEY, $until->getTimestamp(), $until);
        Cache::put(self::CIRCUIT_REASON_KEY, $reason, $until);

        Log::warning("Nominatim {$reason}: se pausan las consultas", $context + [
            'paused_minutes' => intdiv($seconds, 60),
            'until' => $until->toDateTimeString(),
        ]);
    }

    /**
     * Respeta 1 request por segundo entre todos los procesos (la marca vive en la cache).
     */
    private function waitForRateLimit(): void
    {
        $lastRequestAt = (float) Cache::get(self::LAST_REQUEST_KEY, 0);
        $wait = self::MIN_SECONDS_BETWEEN_REQUESTS - (microtime(true) - $lastRequestAt);

        if ($wait > 0) {
            Sleep::usleep((int) ceil($wait * 1000000));
        }

        Cache::put(self::LAST_REQUEST_KEY, microtime(true), 60);
    }

    /**
     * User-Agent propio con contacto, como pide la política de Nominatim (un UA genérico o
     * de librería HTTP puede terminar bloqueado).
     */
    private function userAgent(): string
    {
        $configured = config('services.nominatim.user_agent');
        if ($configured) {
            return $configured;
        }

        $contact = array_filter([config('app.url'), config('mail.from.address')]);

        return sprintf(
            '%s/1.0 geocoding%s',
            Str::studly((string) config('app.name', 'RYR Backend')),
            $contact ? ' (' . implode('; ', $contact) . ')' : ''
        );
    }

    /**
     * Simplifica la dirección removiendo prefijos y normalizando
     */
    private function simplifyAddress(string $address, ?string $city = null): string
    {
        // Remover prefijos comunes y dejar solo el nombre de la calle
        $simplified = preg_replace('/^(BV|BVD|BLVD|BLV|BV\.|AV|AVE|AVDA|AV\.|C|CALLE|C\.|PJE|PJE\.|R|RTA|R\.)\s+/i', '', $address);

        // Limpiar espacios múltiples
        $simplified = preg_replace('/\s+/', ' ', trim($simplified));

        // Construir dirección simplificada
        $parts = array_filter([$simplified, $city]);

        return implode(', ', $parts) . ', Argentina';
    }

    /**
     * Construye la dirección completa combinando address + city
     */
    private function buildFullAddress(string $address, ?string $city = null): string
    {
        // Normalizar la dirección antes de construir la dirección completa
        $normalizedAddress = $this->normalizeAddress($address);
        $parts = array_filter([$normalizedAddress, $city]);

        return implode(', ', $parts) . ', Argentina';
    }

    /**
     * Normaliza direcciones comunes para mejorar los resultados de búsqueda
     */
    private function normalizeAddress(string $address): string
    {
        // Normalizar abreviaciones comunes
        $normalizations = [
            // Boulevard
            '/\bBV\s+/i' => 'Boulevard ',
            '/\bBVD\s+/i' => 'Boulevard ',
            '/\bBLVD\s+/i' => 'Boulevard ',
            '/\bBLV\s+/i' => 'Boulevard ',
            '/\bBV\.\s*/i' => 'Boulevard ',

            // Avenida
            '/\bAV\s+/i' => 'Avenida ',
            '/\bAVE\s+/i' => 'Avenida ',
            '/\bAVDA\s+/i' => 'Avenida ',
            '/\bAV\.\s*/i' => 'Avenida ',

            // Calle
            '/\bC\s+/i' => 'Calle ',
            '/\bCALLE\s+/i' => 'Calle ',
            '/\bC\.\s*/i' => 'Calle ',

            // Pasaje
            '/\bPJE\s+/i' => 'Pasaje ',
            '/\bPJE\.\s*/i' => 'Pasaje ',

            // Ruta
            '/\bR\s+/i' => 'Ruta ',
            '/\bRTA\s+/i' => 'Ruta ',
            '/\bR\.\s*/i' => 'Ruta ',

            // Números romanos comunes
            '/\bXXVII\b/i' => '27',
            '/\bXXVI\b/i' => '26',
            '/\bXXV\b/i' => '25',
            '/\bXXIV\b/i' => '24',
            '/\bXXIII\b/i' => '23',
            '/\bXXII\b/i' => '22',
            '/\bXXI\b/i' => '21',
            '/\bXX\b/i' => '20',
            '/\bXIX\b/i' => '19',
            '/\bXVIII\b/i' => '18',
            '/\bXVII\b/i' => '17',
            '/\bXVI\b/i' => '16',
            '/\bXV\b/i' => '15',
            '/\bXIV\b/i' => '14',
            '/\bXIII\b/i' => '13',
            '/\bXII\b/i' => '12',
            '/\bXI\b/i' => '11',
            '/\bX\b/i' => '10',
            '/\bIX\b/i' => '9',
            '/\bVIII\b/i' => '8',
            '/\bVII\b/i' => '7',
            '/\bVI\b/i' => '6',
            '/\bV\b/i' => '5',
            '/\bIV\b/i' => '4',
            '/\bIII\b/i' => '3',
            '/\bII\b/i' => '2',
            '/\bI\b/i' => '1',
        ];

        $normalized = $address;
        foreach ($normalizations as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }

        // Limpiar espacios múltiples
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        return $normalized;
    }

    /**
     * Obtiene coordenadas para múltiples direcciones de forma eficiente
     * NOTA: el segundo de espera entre requests que pide Nominatim lo aplica searchNominatim(),
     * sólo cuando de verdad se sale a la red (las direcciones cacheadas no esperan).
     */
    public function getMultipleCoordinates(array $addresses): array
    {
        $results = [];

        foreach ($addresses as $key => $addressData) {
            $address = $addressData['address'] ?? '';
            $city = $addressData['city'] ?? null;

            $results[$key] = $this->getCoordinates($address, $city);
        }

        return $results;
    }
}
