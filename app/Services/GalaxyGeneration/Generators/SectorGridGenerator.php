<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Models\Galaxy;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates sector grid overlay for a galaxy.
 *
 * Sectors divide the galaxy into navigable regions for:
 * - Improved query performance
 * - Regional danger levels
 * - Sector-based navigation
 *
 * Uses optimized SQL JOIN for POI assignment.
 */
final class SectorGridGenerator implements GeneratorInterface
{
    private const GREEK_LETTERS = [
        'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta',
        'Iota', 'Kappa', 'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron', 'Pi',
        'Rho', 'Sigma', 'Tau', 'Upsilon', 'Phi', 'Chi', 'Psi', 'Omega',
    ];

    public function getName(): string
    {
        return 'sector_grid';
    }

    public function getDependencies(): array
    {
        return [StarFieldGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        /** @var GenerationConfig $config */
        $config = $context['config'];
        $gridSize = $config->getGridSize();

        $sectorWidth = $galaxy->width / $gridSize;
        $sectorHeight = $galaxy->height / $gridSize;

        // Step 1: Create sector grid and get ID mapping
        $sectorIdMap = $this->createSectorGrid($galaxy, $gridSize, $sectorWidth, $sectorHeight);
        $metrics->setCount('sectors_created', count($sectorIdMap));
        $metrics->setCount('grid_size', $gridSize);

        // Step 2: Assign POIs to sectors using calculated grid positions
        $poisAssigned = $this->assignPoisToSectors($galaxy, $gridSize, $sectorWidth, $sectorHeight);
        $metrics->setCount('pois_assigned', $poisAssigned);

        return GenerationResult::success($metrics, [
            'sectors_created' => count($sectorIdMap),
            'pois_assigned' => $poisAssigned,
        ]);
    }

    /**
     * Create sector grid using batch insert.
     *
     * @return array<string, int> Map of "grid_x,grid_y" => sector_id
     */
    private function createSectorGrid(Galaxy $galaxy, int $gridSize, float $sectorWidth, float $sectorHeight): array
    {
        $now = now()->format('Y-m-d H:i:s');

        $rows = [];
        for ($y = 0; $y < $gridSize; $y++) {
            $rowName = self::GREEK_LETTERS[$y % count(self::GREEK_LETTERS)];
            if ($y >= count(self::GREEK_LETTERS)) {
                $rowName .= '-'.(int) floor($y / count(self::GREEK_LETTERS));
            }

            for ($x = 0; $x < $gridSize; $x++) {
                $rows[] = [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $galaxy->id,
                    'name' => "{$rowName}-".($x + 1),
                    'grid_x' => $x,
                    'grid_y' => $y,
                    'x_min' => $x * $sectorWidth,
                    'x_max' => ($x + 1) * $sectorWidth,
                    'y_min' => $y * $sectorHeight,
                    'y_max' => ($y + 1) * $sectorHeight,
                    'danger_level' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Single batch insert
        DB::table('sectors')->insert($rows);

        // Build ID map for fast lookup
        $sectorIdMap = [];
        $sectors = DB::table('sectors')
            ->where('galaxy_id', $galaxy->id)
            ->select(['id', 'grid_x', 'grid_y'])
            ->get();

        foreach ($sectors as $sector) {
            $sectorIdMap["{$sector->grid_x},{$sector->grid_y}"] = $sector->id;
        }

        return $sectorIdMap;
    }

    /**
     * Assign POIs to sectors using calculated grid positions.
     * Uses subquery with indexed grid columns instead of range-based JOIN.
     */
    private function assignPoisToSectors(Galaxy $galaxy, int $gridSize, float $sectorWidth, float $sectorHeight): int
    {
        // Use LEAST/MIN to clamp grid position to valid range (handles edge case of POI at exact boundary)
        $maxGridIndex = $gridSize - 1;

        // SQLite doesn't support table aliases in UPDATE statements
        // Use MIN instead of LEAST for cross-database compatibility
        if (DB::getDriverName() === 'sqlite') {
            return DB::update('
                UPDATE points_of_interest
                SET sector_id = (
                    SELECT s.id FROM sectors s
                    WHERE s.galaxy_id = points_of_interest.galaxy_id
                    AND s.grid_x = MIN(CAST(points_of_interest.x / ? AS INTEGER), ?)
                    AND s.grid_y = MIN(CAST(points_of_interest.y / ? AS INTEGER), ?)
                    LIMIT 1
                )
                WHERE galaxy_id = ?
            ', [$sectorWidth, $maxGridIndex, $sectorHeight, $maxGridIndex, $galaxy->id]);
        }

        // MySQL/MariaDB version with LEAST and FLOOR
        return DB::update('
            UPDATE points_of_interest poi
            SET sector_id = (
                SELECT s.id FROM sectors s
                WHERE s.galaxy_id = poi.galaxy_id
                AND s.grid_x = LEAST(FLOOR(poi.x / ?), ?)
                AND s.grid_y = LEAST(FLOOR(poi.y / ?), ?)
                LIMIT 1
            )
            WHERE poi.galaxy_id = ?
        ', [$sectorWidth, $maxGridIndex, $sectorHeight, $maxGridIndex, $galaxy->id]);
    }
}
