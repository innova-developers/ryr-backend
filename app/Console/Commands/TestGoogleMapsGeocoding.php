<?php

namespace App\Console\Commands;

use App\Services\GoogleMapsService;
use Illuminate\Console\Command;

class TestGoogleMapsGeocoding extends Command
{
    protected $signature = 'test:google-maps {address} {city?}';
    protected $description = 'Test Google Maps geocoding functionality';

    public function handle()
    {
        $address = $this->argument('address');
        $city = $this->argument('city');

        $this->info("Testing geocoding for: {$address}" . ($city ? ", {$city}" : ""));

        $googleMapsService = new GoogleMapsService();
        $coordinates = $googleMapsService->getCoordinates($address, $city);

        if ($coordinates) {
            $this->info("✅ Coordinates found:");
            $this->line("Latitude: {$coordinates['latitude']}");
            $this->line("Longitude: {$coordinates['longitude']}");
        } else {
            $this->error("❌ No coordinates found. Check your Google Maps API key and address.");
        }
    }
}
