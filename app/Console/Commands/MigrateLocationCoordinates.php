<?php

namespace App\Console\Commands;

use App\Shared\Models\Location;
use App\Services\GoogleMapsService;
use Illuminate\Console\Command;

class MigrateLocationCoordinates extends Command
{
    protected $signature = 'migrate:location-coordinates {--batch=10 : Number of locations to process per batch} {--delay=1 : Delay in seconds between API calls}';
    protected $description = 'Migrate coordinates for existing locations using Google Maps API';

    public function handle()
    {
        $batchSize = (int) $this->option('batch');
        $delay = (int) $this->option('delay');
        
        $this->info('Starting location coordinates migration...');
        
        // Get locations without coordinates
        $totalLocations = Location::whereNull('latitude')
            ->orWhereNull('longitude')
            ->count();
            
        if ($totalLocations === 0) {
            $this->info('All locations already have coordinates.');
            return;
        }
        
        $this->info("Found {$totalLocations} locations without coordinates.");
        
        $googleMapsService = new GoogleMapsService();
        $processed = 0;
        $successful = 0;
        $failed = 0;
        
        // Process locations in batches
        Location::whereNull('latitude')
            ->orWhereNull('longitude')
            ->chunk($batchSize, function ($locations) use ($googleMapsService, &$processed, &$successful, &$failed, $delay) {
                foreach ($locations as $location) {
                    $this->info("Processing location ID {$location->id}: {$location->name}");
                    
                    try {
                        $coordinates = $googleMapsService->getCoordinates(
                            $location->address,
                            $location->origin
                        );
                        
                        if ($coordinates) {
                            $location->update([
                                'latitude' => $coordinates['latitude'],
                                'longitude' => $coordinates['longitude']
                            ]);
                            
                            $this->line("  ✅ Coordinates: {$coordinates['latitude']}, {$coordinates['longitude']}");
                            $successful++;
                        } else {
                            $this->error("  ❌ Could not get coordinates");
                            $failed++;
                        }
                        
                        $processed++;
                        
                        // Delay to avoid hitting API rate limits
                        if ($delay > 0) {
                            sleep($delay);
                        }
                        
                    } catch (\Exception $e) {
                        $this->error("  ❌ Error: {$e->getMessage()}");
                        $failed++;
                        $processed++;
                    }
                }
            });
        
        $this->info("\nMigration completed!");
        $this->info("Processed: {$processed}");
        $this->info("Successful: {$successful}");
        $this->info("Failed: {$failed}");
        
        if ($failed > 0) {
            $this->warn("Some locations failed to get coordinates. Check the logs for details.");
        }
    }
}