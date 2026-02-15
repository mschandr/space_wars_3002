<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\PirateBand;
use App\Models\PirateCaptain;
use App\Models\PointOfInterest;
use App\Models\Sector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GalaxyDistributePirateBands extends Command
{
    protected $signature = 'galaxy:distribute-pirate-bands
                            {galaxy : Galaxy ID or name}
                            {--min-per-sector=1 : Minimum pirate bands per sector}
                            {--max-per-sector=3 : Maximum pirate bands per sector}
                            {--regenerate : Remove existing bands and regenerate}';

    protected $description = 'Distribute mobile pirate bands across sectors in a galaxy';

    public function handle(): int
    {
        $galaxyIdentifier = $this->argument('galaxy');

        $galaxy = is_numeric($galaxyIdentifier)
            ? Galaxy::find($galaxyIdentifier)
            : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

        if (! $galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");

            return Command::FAILURE;
        }

        $captains = PirateCaptain::all();
        if ($captains->isEmpty()) {
            $this->error('No pirate captains found. Run PirateCaptainSeeder first.');

            return Command::FAILURE;
        }

        $sectors = Sector::where('galaxy_id', $galaxy->id)->get();
        if ($sectors->isEmpty()) {
            $this->error("Galaxy '{$galaxy->name}' has no sectors.");

            return Command::FAILURE;
        }

        if ($this->option('regenerate')) {
            $deleted = PirateBand::where('galaxy_id', $galaxy->id)->delete();
            if ($deleted > 0) {
                $this->info("Deleted {$deleted} existing pirate bands");
            }
        }

        $this->info("Distributing pirate bands across galaxy: {$galaxy->name}");
        $this->info("Sectors: {$sectors->count()}");
        $this->newLine();

        $minPerSector = (int) $this->option('min-per-sector');
        $maxPerSector = (int) $this->option('max-per-sector');
        $bandsCreated = 0;
        $sectorsSkipped = 0;

        foreach ($sectors as $sector) {
            // Find uninhabited systems in this sector for home bases
            $uninhabitedSystems = PointOfInterest::where('sector_id', $sector->id)
                ->where('galaxy_id', $galaxy->id)
                ->stars()
                ->uninhabited()
                ->where('is_hidden', false)
                ->get();

            if ($uninhabitedSystems->isEmpty()) {
                $sectorsSkipped++;

                continue;
            }

            // Determine how many bands for this sector
            // Outer sectors get more bands
            $centerX = $galaxy->width / 2.0;
            $centerY = $galaxy->height / 2.0;
            $sectorCenterX = ($sector->x_min + $sector->x_max) / 2;
            $sectorCenterY = ($sector->y_min + $sector->y_max) / 2;
            $distFromCenter = sqrt(pow($sectorCenterX - $centerX, 2) + pow($sectorCenterY - $centerY, 2));
            $maxDist = sqrt($centerX * $centerX + $centerY * $centerY);
            $normalizedDist = $distFromCenter / max(1, $maxDist);

            // Outer sectors get up to max, inner sectors get min
            $bandCount = (int) round($minPerSector + ($maxPerSector - $minPerSector) * $normalizedDist);
            $bandCount = min($bandCount, $uninhabitedSystems->count());

            $homeBases = $uninhabitedSystems->random(min($bandCount, $uninhabitedSystems->count()));

            foreach ($homeBases as $homeBase) {
                $captain = $captains->random();

                // Calculate roaming radius based on sector size
                $sectorWidth = $sector->x_max - $sector->x_min;
                $sectorHeight = $sector->y_max - $sector->y_min;
                $roamingRadius = max($sectorWidth, $sectorHeight) / 2;

                PirateBand::create([
                    'uuid' => Str::uuid(),
                    'galaxy_id' => $galaxy->id,
                    'sector_id' => $sector->id,
                    'home_base_poi_id' => $homeBase->id,
                    'captain_id' => $captain->id,
                    'fleet_size' => rand(1, 4),
                    'difficulty_tier' => rand(1, 5),
                    'is_active' => true,
                    'current_poi_id' => $homeBase->id,
                    'last_moved_at' => now(),
                    'roaming_radius_ly' => $roamingRadius,
                ]);

                $bandsCreated++;
            }
        }

        $this->newLine();
        $this->info('Pirate band distribution complete!');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Sectors', $sectors->count()],
                ['Sectors Skipped (no uninhabited)', $sectorsSkipped],
                ['Pirate Bands Created', $bandsCreated],
            ]
        );

        return Command::SUCCESS;
    }
}
