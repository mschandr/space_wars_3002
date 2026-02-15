<?php

namespace App\Console\Commands;

use App\Models\Galaxy;
use App\Models\PirateBand;
use App\Models\PointOfInterest;
use Illuminate\Console\Command;

class PiratesMoveCommand extends Command
{
    protected $signature = 'pirates:move
                            {galaxy? : Galaxy ID or name (all galaxies if omitted)}';

    protected $description = 'Move pirate bands to new positions within their sectors';

    public function handle(): int
    {
        $galaxyIdentifier = $this->argument('galaxy');

        $query = PirateBand::active();

        if ($galaxyIdentifier) {
            $galaxy = is_numeric($galaxyIdentifier)
                ? Galaxy::find($galaxyIdentifier)
                : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

            if (! $galaxy) {
                $this->error("Galaxy not found: {$galaxyIdentifier}");

                return Command::FAILURE;
            }

            $query->where('galaxy_id', $galaxy->id);
            $this->info("Moving pirate bands in galaxy: {$galaxy->name}");
        } else {
            $this->info('Moving pirate bands in all galaxies');
        }

        $bands = $query->with(['homeBase', 'sector'])->get();
        $moved = 0;
        $stayed = 0;

        foreach ($bands as $band) {
            // 50% chance to move each cycle
            if (rand(1, 100) > 50) {
                $stayed++;

                continue;
            }

            $roamingRadius = $band->roaming_radius_ly ?? 50;
            $homeBase = $band->homeBase;

            if (! $homeBase) {
                $stayed++;

                continue;
            }

            // Find systems within roaming radius of home base in the same sector
            $candidates = PointOfInterest::where('galaxy_id', $band->galaxy_id)
                ->where('sector_id', $band->sector_id)
                ->stars()
                ->where('is_hidden', false)
                ->where('id', '!=', $band->current_poi_id)
                ->where('x', '>=', $homeBase->x - $roamingRadius)
                ->where('x', '<=', $homeBase->x + $roamingRadius)
                ->where('y', '>=', $homeBase->y - $roamingRadius)
                ->where('y', '<=', $homeBase->y + $roamingRadius)
                ->whereRaw(
                    'SQRT(POW(CAST(x AS SIGNED) - ?, 2) + POW(CAST(y AS SIGNED) - ?, 2)) <= ?',
                    [$homeBase->x, $homeBase->y, $roamingRadius]
                )
                ->inRandomOrder()
                ->limit(1)
                ->first();

            if ($candidates) {
                $band->relocate($candidates);
                $moved++;
            } else {
                $stayed++;
            }
        }

        $this->newLine();
        $this->info('Pirate movement complete!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Active Bands', $bands->count()],
                ['Bands Moved', $moved],
                ['Bands Stayed', $stayed],
            ]
        );

        return Command::SUCCESS;
    }
}
