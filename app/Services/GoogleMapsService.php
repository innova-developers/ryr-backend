<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    private string $apiKey;
    private string $baseUrl = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key');
    }

    /**
     * Obtiene las coordenadas de una dirección usando Google Maps Geocoding API
     */
    public function getCoordinates(string $address, string $city = null): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('Google Maps API key not configured');

            return null;
        }

        // Construir dirección completa
        $fullAddress = $this->buildFullAddress($address, $city);

        // Verificar cache primero
        $cacheKey = 'geocode_' . md5($fullAddress);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get($this->baseUrl, [
                'address' => $fullAddress,
                'key' => $this->apiKey,
                'region' => 'AR', // Argentina
                'language' => 'es',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'OK' && ! empty($data['results'])) {
                    $location = $data['results'][0]['geometry']['location'];
                    $coordinates = [
                        'latitude' => $location['lat'],
                        'longitude' => $location['lng'],
                    ];

                    // Cachear por 24 horas
                    Cache::put($cacheKey, $coordinates, now()->addHours(24));

                    return $coordinates;
                } else {
                    Log::warning('Google Maps geocoding failed', [
                        'address' => $fullAddress,
                        'status' => $data['status'] ?? 'UNKNOWN',
                        'error_message' => $data['error_message'] ?? 'No error message',
                    ]);
                }
            } else {
                Log::error('Google Maps API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Google Maps geocoding exception', [
                'address' => $fullAddress,
                'error' => $e->getMessage(),
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
     */
    public function getMultipleCoordinates(array $addresses): array
    {
        $results = [];

        foreach ($addresses as $key => $addressData) {
            $address = $addressData['address'] ?? '';
            $city = $addressData['city'] ?? null;

            $coordinates = $this->getCoordinates($address, $city);
            $results[$key] = $coordinates;
        }

        return $results;
    }
}
