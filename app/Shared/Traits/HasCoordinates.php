<?php

namespace App\Shared\Traits;

use App\Services\GoogleMapsService;
use Illuminate\Support\Facades\Log;

trait HasCoordinates
{
    /**
     * Boot the trait and set up event listeners
     */
    protected static function bootHasCoordinates()
    {
        static::saving(function ($model) {
            $model->updateCoordinatesIfNeeded();
        });
    }

    /**
     * Update coordinates if address or origin has changed
     */
    public function updateCoordinatesIfNeeded()
    {
        // Check if address or origin fields have changed
        if ($this->isDirty(['address', 'origin'])) {
            $this->calculateAndSetCoordinates();
        }
    }

    /**
     * Calculate and set coordinates using Google Maps API
     */
    public function calculateAndSetCoordinates()
    {
        try {
            $googleMapsService = new GoogleMapsService();
            $coordinates = $googleMapsService->getCoordinates(
                $this->address ?? '',
                $this->origin ?? null
            );

            if ($coordinates) {
                $this->latitude = $coordinates['latitude'];
                $this->longitude = $coordinates['longitude'];
                
                Log::info('Coordinates calculated for location', [
                    'location_id' => $this->id,
                    'address' => $this->address,
                    'origin' => $this->origin,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude
                ]);
            } else {
                Log::warning('Could not calculate coordinates for location', [
                    'location_id' => $this->id,
                    'address' => $this->address,
                    'origin' => $this->origin
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error calculating coordinates for location', [
                'location_id' => $this->id,
                'address' => $this->address,
                'origin' => $this->origin,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get coordinates array
     */
    public function getCoordinates(): ?array
    {
        if ($this->latitude && $this->longitude) {
            return [
                'latitude' => $this->latitude,
                'longitude' => $this->longitude
            ];
        }

        return null;
    }

    /**
     * Check if coordinates are available
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }
}
