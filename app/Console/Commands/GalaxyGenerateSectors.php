<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\Sector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GalaxyGenerateSectors extends Command
{
    protected $signature = 'galaxy:generate-sectors
                            {galaxy : Galaxy ID or name}
                            {--grid-size=10 : Grid size (10 = 10x10 sectors)}
                            {--regenerate : Delete existing sectors and regenerate}';

    protected $description = 'Generate sectors for a galaxy to improve navigation performance';

    public function handle(): int
    {
        $galaxyIdentifier = $this->argument('galaxy');

        // Find galaxy
        $galaxy = is_numeric($galaxyIdentifier)
            ? Galaxy::find($galaxyIdentifier)
            : Galaxy::where('name', 'like', "%{$galaxyIdentifier}%")->first();

        if (!$galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");
            return Command::FAILURE;
        }

        $gridSize = max(2, min(20, (int)$this->option('grid-size')));

        $this->info("Generating sectors for galaxy: {$galaxy->name}");
        $this->info("Galaxy size: {$galaxy->width}x{$galaxy->height}");
        $this->info("Grid: {$gridSize}x{$gridSize} sectors");
        $this->newLine();

        // Delete existing sectors if regenerating
        if ($this->option('regenerate')) {
            $existingCount = $galaxy->sectors()->count();
            if ($existingCount > 0) {
                // Reset sector_id on POIs
                PointOfInterest::where('galaxy_id', $galaxy->id)
                    ->update(['sector_id' => null]);

                $galaxy->sectors()->delete();
                $this->info("Deleted {$existingCount} existing sectors");
            }
        }

        // Generate sector grid
        $sectors = $this->generateSectorGrid($galaxy, $gridSize);

        // Assign POIs to sectors
        $this->assignPoisToSectors($galaxy, $sectors);

        // Show summary
        $this->showSummary($galaxy, $sectors);

        return Command::SUCCESS;
    }

    private function generateSectorGrid(Galaxy $galaxy, int $gridSize): array
    {
        $this->info("Creating {$gridSize}x{$gridSize} sector grid...");

        $sectorWidth = $galaxy->width / $gridSize;
        $sectorHeight = $galaxy->height / $gridSize;

        $sectors = [];
        $sectorNames = $this->generateSectorNames($gridSize);

        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                $sector = Sector::create([
                    'uuid' => Str::uuid(),
                    'galaxy_id' => $galaxy->id,
                    'name' => $sectorNames[$y][$x],
                    'grid_x' => $x,
                    'grid_y' => $y,
                    'x_min' => $x * $sectorWidth,
                    'x_max' => ($x + 1) * $sectorWidth,
                    'y_min' => $y * $sectorHeight,
                    'y_max' => ($y + 1) * $sectorHeight,
                ]);

                $sectors[] = $sector;
            }
        }

        $this->info("Created " . count($sectors) . " sectors");
        return $sectors;
    }

    private function assignPoisToSectors(Galaxy $galaxy, array $sectors): void
    {
        $this->newLine();
        $this->info("Assigning POIs to sectors...");

        $totalPois = $galaxy->pointsOfInterest()->count();
        $assigned = 0;
        $chunkSize = 500;

        $progressBar = $this->output->createProgressBar($totalPois);
        $progressBar->start();

        PointOfInterest::where('galaxy_id', $galaxy->id)
            ->chunk($chunkSize, function ($pois) use ($sectors, &$assigned, $progressBar) {
                foreach ($pois as $poi) {
                    // Find which sector contains this POI
                    foreach ($sectors as $sector) {
                        if ($sector->containsCoordinates($poi->x, $poi->y)) {
                            $poi->sector_id = $sector->id;
                            $poi->save();
                            $assigned++;
                            break;
                        }
                    }
                    $progressBar->advance();
                }
            });

        $progressBar->finish();
        $this->newLine();
        $this->info("Assigned {$assigned} POIs to sectors");
    }

    private function generateSectorNames(int $gridSize): array
    {
        // Generate sector names like "Alpha-1", "Beta-2", etc.
        $greekLetters = [
            'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta',
            'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi',
            'Rho', 'Sigma', 'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega'
        ];

        $names = [];
        for ($y = 0; $y < $gridSize; $y++) {
            $names[$y] = [];
            for ($x = 0; $x < $gridSize; $x++) {
                // Use Greek letters for rows, numbers for columns
                $rowName = $greekLetters[$y % count($greekLetters)];
                if ($y >= count($greekLetters)) {
                    $rowName .= '-' . floor($y / count($greekLetters));
                }
                $names[$y][$x] = "{$rowName}-" . ($x + 1);
            }
        }

        return $names;
    }

    private function showSummary(Galaxy $galaxy, array $sectors): void
    {
        $this->newLine();
        $this->info("✅ Sector generation complete!");
        $this->newLine();

        // Calculate statistics
        $totalSectors = count($sectors);
        $sectorsWithStars = 0;
        $avgStarsPerSector = 0;
        $totalStars = 0;

        foreach ($sectors as $sector) {
            $starCount = $sector->pointsOfInterest()
                ->where('type', PointOfInterestType::STAR)
                ->count();

            if ($starCount > 0) {
                $sectorsWithStars++;
                $totalStars += $starCount;
            }
        }

        $avgStarsPerSector = $sectorsWithStars > 0 ? round($totalStars / $sectorsWithStars, 1) : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Sectors', $totalSectors],
                ['Sectors with Stars', $sectorsWithStars],
                ['Empty Sectors', $totalSectors - $sectorsWithStars],
                ['Total Stars', $totalStars],
                ['Avg Stars per Sector', $avgStarsPerSector],
            ]
        );

        $this->newLine();
        $this->info("Use `php artisan galaxy:view {$galaxy->id}` to view the sectored galaxy");
    }
}
