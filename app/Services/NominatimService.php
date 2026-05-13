<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NominatimService
{
    private string $baseUrl = 'https://nominatim.openstreetmap.org/search';

    /**
     * Obtiene las coordenadas de una dirección usando Nominatim (OpenStreetMap)
     *
     * IMPORTANTE: Nominatim requiere un User-Agent identificando tu aplicación.
     * Sin esto, pueden bloquear tus requests.
     */
    public function getCoordinates(string $address, string $city = null): ?array
    {
        // Construir dirección completa normalizada
        $fullAddress = $this->buildFullAddress($address, $city);

        // Verificar cache primero
        $cacheKey = 'geocode_nominatim_' . md5($fullAddress);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        // Intentar búsqueda con dirección normalizada
        $coordinates = $this->searchNominatim($fullAddress, $cacheKey);

        // Si no se encontraron resultados, intentar con versión simplificada
        if (! $coordinates && $address) {
            $simplifiedAddress = $this->simplifyAddress($address, $city);
            if ($simplifiedAddress !== $fullAddress) {
                $simplifiedCacheKey = 'geocode_nominatim_' . md5($simplifiedAddress);
                $cachedSimplified = Cache::get($simplifiedCacheKey);

                if ($cachedSimplified) {
                    return $cachedSimplified;
                }

                $coordinates = $this->searchNominatim($simplifiedAddress, $simplifiedCacheKey);
            }
        }

        return $coordinates;
    }

    /**
     * Realiza la búsqueda en Nominatim
     */
    private function searchNominatim(string $fullAddress, string $cacheKey): ?array
    {
        try {
            // Nominatim requiere un User-Agent identificando tu aplicación
            // También requiere un delay mínimo de 1 segundo entre requests (rate limiting)
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'RYR Backend') . ' Geocoding Service',
                    'Accept-Language' => 'es-AR,es,en',
                ])
                ->get($this->baseUrl, [
                    'q' => $fullAddress,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'countrycodes' => 'ar', // Argentina
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (is_array($data) && ! empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    $coordinates = [
                        'latitude' => (float) $data[0]['lat'],
                        'longitude' => (float) $data[0]['lon'],
                    ];

                    // Cachear por 30 días (Nominatim permite cachear resultados)
                    Cache::put($cacheKey, $coordinates, now()->addDays(30));

                    Log::info('Coordenadas obtenidas de Nominatim', [
                        'address' => $fullAddress,
                        'latitude' => $coordinates['latitude'],
                        'longitude' => $coordinates['longitude'],
                    ]);

                    return $coordinates;
                } else {
                    Log::warning('Nominatim no encontró resultados', [
                        'address' => $fullAddress,
                        'response' => $data,
                    ]);
                }
            } else {
                Log::error('Error en request a Nominatim', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al obtener coordenadas de Nominatim', [
                'address' => $fullAddress,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Simplifica la dirección removiendo prefijos y normalizando
     */
    private function simplifyAddress(string $address, string $city = null): string
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
    private function buildFullAddress(string $address, string $city = null): string
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
     * NOTA: Nominatim requiere 1 segundo de delay entre requests
     */
    public function getMultipleCoordinates(array $addresses): array
    {
        $results = [];

        foreach ($addresses as $key => $addressData) {
            $address = $addressData['address'] ?? '';
            $city = $addressData['city'] ?? null;

            $coordinates = $this->getCoordinates($address, $city);
            $results[$key] = $coordinates;

            // Rate limiting: esperar 1 segundo entre requests
            if ($key < count($addresses) - 1) {
                sleep(1);
            }
        }

        return $results;
    }
}
