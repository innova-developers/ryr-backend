<?php

namespace App\Console\Commands;

use App\Services\NominatimService;
use Illuminate\Console\Command;

class TestNominatimGeocoding extends Command
{
    protected $signature = 'test:nominatim {address} {city?}';
    protected $description = 'Test Nominatim (OpenStreetMap) geocoding functionality';

    public function handle()
    {
        $address = $this->argument('address');
        $city = $this->argument('city');

        $this->info("Testing Nominatim geocoding for: {$address}" . ($city ? ", {$city}" : ""));
        $this->newLine();

        $nominatimService = new NominatimService();

        $startTime = microtime(true);
        $coordinates = $nominatimService->getCoordinates($address, $city);
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        if ($coordinates) {
            $this->info("✅ Coordinates found:");
            $this->line("   Latitude: {$coordinates['latitude']}");
            $this->line("   Longitude: {$coordinates['longitude']}");
            $this->line("   Response time: {$duration}ms");
            $this->newLine();
            $this->info("📍 Google Maps link: https://www.google.com/maps?q={$coordinates['latitude']},{$coordinates['longitude']}");
        } else {
            $this->error("❌ No coordinates found.");
            $this->line("   Check the address format and try again.");
            $this->line("   Response time: {$duration}ms");
        }
    }
}
