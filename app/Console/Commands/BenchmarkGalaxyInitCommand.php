<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\Sector;
use App\Services\InhabitedSystemGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BenchmarkGalaxyInitCommand extends Command
{
    protected $signature = 'benchmark:galaxy-init
                            {--stars=500 : Number of stars to benchmark}
                            {--grid-size=10 : Sector grid size}';

    protected $description = 'Benchmark galaxy initialization performance';

    private Galaxy $galaxy;

    public function handle(): int
    {
        $starCount = (int) $this->option('stars');
        $gridSize = (int) $this->option('grid-size');

        $this->info("Setting up benchmark with {$starCount} stars and {$gridSize}x{$gridSize} sectors...");
        $this->newLine();

        // Create test galaxy
        $this->galaxy = Galaxy::create([
            'uuid' => Str::uuid(),
            'name' => 'Benchmark Galaxy '.Str::random(4),
            'width' => 1000,
            'height' => 1000,
            'seed' => random_int(1, 999999),
            'distribution_method' => GalaxyDistributionMethod::RANDOM_SCATTER,
            'engine' => GalaxyRandomEngine::MT19937,
            'status' => GalaxyStatus::ACTIVE,
            'turn_limit' => 0,
            'is_public' => false,
        ]);

        $this->info('=== BENCHMARK RESULTS ===');
        $this->newLine();

        // Benchmark 1: POI Creation
        $this->info('1. POI Creation (createPointsForGalaxy)');
        $this->benchmarkPoiCreation($starCount);

        // Benchmark 2: Sector Grid Creation
        $this->info('2. Sector Grid Creation');
        $this->benchmarkSectorCreation($gridSize);

        // Benchmark 3: Sector Assignment
        $this->info('3. Sector Assignment (assignPoisToSectors)');
        $this->benchmarkSectorAssignment($gridSize);

        // Benchmark 4: Inhabited Designation
        $this->info('4. Inhabited Designation (40% of stars)');
        $this->benchmarkInhabitedDesignation();

        // Cleanup
        $this->newLine();
        $this->info('Cleaning up benchmark data...');
        $this->cleanup();

        $this->info('Benchmark complete!');

        return Command::SUCCESS;
    }

    private function benchmarkPoiCreation(int $starCount): void
    {
        // Generate random points
        $points = [];
        for ($i = 0; $i < $starCount; $i++) {
            $points[] = [rand(0, 999), rand(0, 999)];
        }

        DB::enableQueryLog();
        $startTime = microtime(true);

        PointOfInterest::createPointsForGalaxy($this->galaxy, $points);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $poiCount = PointOfInterest::where('galaxy_id', $this->galaxy->id)->count();
        $this->displayResults($endTime - $startTime, count($queries), "{$poiCount} POIs created");
    }

    private function benchmarkSectorCreation(int $gridSize): void
    {
        $sectorWidth = $this->galaxy->width / $gridSize;
        $sectorHeight = $this->galaxy->height / $gridSize;
        $now = now();

        // Pre-generate all sector data for batch insert (matching optimized version)
        $batchData = [];
        for ($y = 0; $y < $gridSize; $y++) {
            for ($x = 0; $x < $gridSize; $x++) {
                $batchData[] = [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $this->galaxy->id,
                    'name' => "Sector-{$x}-{$y}",
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

        DB::enableQueryLog();
        $startTime = microtime(true);

        // Single batch insert
        DB::table('sectors')->insert($batchData);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $sectorCount = Sector::where('galaxy_id', $this->galaxy->id)->count();
        $this->displayResults($endTime - $startTime, count($queries), "{$sectorCount} sectors created");
    }

    private function benchmarkSectorAssignment(int $gridSize): void
    {
        DB::enableQueryLog();
        $startTime = microtime(true);

        // OPTIMIZED: Single UPDATE with JOIN instead of N individual updates
        $assigned = DB::update("
            UPDATE points_of_interest poi
            INNER JOIN sectors s ON s.galaxy_id = poi.galaxy_id
                AND poi.x >= s.x_min AND poi.x < s.x_max
                AND poi.y >= s.y_min AND poi.y < s.y_max
            SET poi.sector_id = s.id
            WHERE poi.galaxy_id = ?
        ", [$this->galaxy->id]);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), "{$assigned} POIs assigned to sectors");
    }

    private function benchmarkInhabitedDesignation(): void
    {
        $generator = app(InhabitedSystemGenerator::class);

        DB::enableQueryLog();
        $startTime = microtime(true);

        $inhabited = $generator->designateInhabitedSystems($this->galaxy, 0.40, 50);

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->displayResults($endTime - $startTime, count($queries), "{$inhabited->count()} systems marked inhabited");
    }

    private function displayResults(float $duration, int $queryCount, string $extra = ''): void
    {
        $durationMs = round($duration * 1000, 2);
        $this->line("   Duration: <comment>{$durationMs}ms</comment>");
        $this->line("   Queries:  <comment>{$queryCount}</comment>");
        if ($extra) {
            $this->line("   Result:   <comment>{$extra}</comment>");
        }
        $this->newLine();
    }

    private function cleanup(): void
    {
        PointOfInterest::where('galaxy_id', $this->galaxy->id)->delete();
        Sector::where('galaxy_id', $this->galaxy->id)->delete();
        Galaxy::where('id', $this->galaxy->id)->delete();
    }
}
