<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
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
        // Construir dirección completa
        $fullAddress = $this->buildFullAddress($address, $city);
        
        // Verificar cache primero
        $cacheKey = 'geocode_nominatim_' . md5($fullAddress);
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return $cached;
        }

        try {
            // Nominatim requiere un User-Agent identificando tu aplicación
            // También requiere un delay mínimo de 1 segundo entre requests (rate limiting)
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'RYR Backend') . ' Geocoding Service',
                    'Accept-Language' => 'es-AR,es,en'
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
                
                if (is_array($data) && !empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    $coordinates = [
                        'latitude' => (float) $data[0]['lat'],
                        'longitude' => (float) $data[0]['lon']
                    ];
                    
                    // Cachear por 30 días (Nominatim permite cachear resultados)
                    Cache::put($cacheKey, $coordinates, now()->addDays(30));
                    
                    Log::info('Coordenadas obtenidas de Nominatim', [
                        'address' => $fullAddress,
                        'latitude' => $coordinates['latitude'],
                        'longitude' => $coordinates['longitude']
                    ]);
                    
                    return $coordinates;
                } else {
                    Log::warning('Nominatim no encontró resultados', [
                        'address' => $fullAddress,
                        'response' => $data
                    ]);
                }
            } else {
                Log::error('Error en request a Nominatim', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al obtener coordenadas de Nominatim', [
                'address' => $fullAddress,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Construye la dirección completa combinando address + city
     */
    private function buildFullAddress(string $address, string $city = null): string
    {
        $parts = array_filter([$address, $city]);
        return implode(', ', $parts) . ', Argentina';
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

