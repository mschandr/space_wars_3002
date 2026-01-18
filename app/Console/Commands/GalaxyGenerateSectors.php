<?php

namespace App\Console\Commands;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\Sector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

        if (! $galaxy) {
            $this->error("Galaxy not found: {$galaxyIdentifier}");

            return Command::FAILURE;
        }

        $gridSize = max(2, min(20, (int) $this->option('grid-size')));

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

    /**
     * Generate sector grid using batch insert
     * OPTIMIZED: Single batch insert instead of individual creates
     */
    private function generateSectorGrid(Galaxy $galaxy, int $gridSize): array
    {
        $this->info("Creating {$gridSize}x{$gridSize} sector grid...");

        $sectorWidth = $galaxy->width / $gridSize;
        $sectorHeight = $galaxy->height / $gridSize;

        $sectorNames = $this->generateSectorNames($gridSize);
        $now = now();

        // Pre-generate all sector data for batch insert
        $batchData = [];
        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                $batchData[] = [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $galaxy->id,
                    'name' => $sectorNames[$y][$x],
                    'grid_x' => $x,
                    'grid_y' => $y,
                    'x_min' => $x * $sectorWidth,
                    'x_max' => ($x + 1) * $sectorWidth,
                    'y_min' => $y * $sectorHeight,
                    'y_max' => ($y + 1) * $sectorHeight,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Single batch insert
        DB::table('sectors')->insert($batchData);

        // Load the created sectors for return
        $sectors = Sector::where('galaxy_id', $galaxy->id)->get()->all();

        $this->info('Created '.count($sectors).' sectors');

        return $sectors;
    }

    /**
     * Assign POIs to sectors using optimized SQL JOIN
     * OPTIMIZED: Single UPDATE with JOIN instead of N individual updates
     */
    private function assignPoisToSectors(Galaxy $galaxy, array $sectors): void
    {
        $this->newLine();
        $this->info('Assigning POIs to sectors...');

        $totalPois = $galaxy->pointsOfInterest()->count();

        // Use a single SQL UPDATE with JOIN for massive performance improvement
        // This replaces O(n×m) PHP iterations with a single O(n) database operation
        $assigned = DB::update("
            UPDATE points_of_interest poi
            INNER JOIN sectors s ON s.galaxy_id = poi.galaxy_id
                AND poi.x >= s.x_min AND poi.x < s.x_max
                AND poi.y >= s.y_min AND poi.y < s.y_max
            SET poi.sector_id = s.id
            WHERE poi.galaxy_id = ?
        ", [$galaxy->id]);

        $this->info("Assigned {$assigned} POIs to sectors (of {$totalPois} total)");
    }

    private function generateSectorNames(int $gridSize): array
    {
        // Generate sector names like "Alpha-1", "Beta-2", etc.
        $greekLetters = [
            'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta',
            'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi',
            'Rho', 'Sigma', 'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega',
        ];

        $names = [];
        for ($y = 0; $y < $gridSize; $y++) {
            $names[$y] = [];
            for ($x = 0; $x < $gridSize; $x++) {
                // Use Greek letters for rows, numbers for columns
                $rowName = $greekLetters[$y % count($greekLetters)];
                if ($y >= count($greekLetters)) {
                    $rowName .= '-'.floor($y / count($greekLetters));
                }
                $names[$y][$x] = "{$rowName}-".($x + 1);
            }
        }

        return $names;
    }

    /**
     * Show summary statistics
     * OPTIMIZED: Single aggregate query instead of N+1 per-sector queries
     */
    private function showSummary(Galaxy $galaxy, array $sectors): void
    {
        $this->newLine();
        $this->info('✅ Sector generation complete!');
        $this->newLine();

        $totalSectors = count($sectors);

        // Use single aggregate query instead of N+1 pattern
        $stats = DB::table('points_of_interest')
            ->where('galaxy_id', $galaxy->id)
            ->where('type', PointOfInterestType::STAR)
            ->whereNotNull('sector_id')
            ->selectRaw('COUNT(*) as total_stars')
            ->selectRaw('COUNT(DISTINCT sector_id) as sectors_with_stars')
            ->first();

        $totalStars = (int) $stats->total_stars;
        $sectorsWithStars = (int) $stats->sectors_with_stars;
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
