<?php

namespace App\Services;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Faker\Providers\StarNameProvider;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\PointOfInterest;
use App\Models\WarpGate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates outer (frontier) region systems for tiered galaxies.
 *
 * Outer systems are:
 * - 0% inhabited (wilderness)
 * - No defenses
 * - Large stars with many planets, moons, and ice giants
 * - Rich mineral deposits (2x richness)
 * - Dormant warp gates requiring activation
 *
 * Performance: Uses bulk inserts for O(n) complexity.
 */
class OuterSystemGenerator
{
    private const CHUNK_SIZE = 500;

    /**
     * Create and persist outer-region star points for a tiered galaxy.
     *
     * Generates star coordinates outside the provided core bounds, inserts corresponding
     * PointOfInterest records in bulk, and returns the created outer-region star POIs.
     *
     * @param Galaxy $galaxy The galaxy to populate.
     * @param int $starCount Number of stars to generate.
     * @param array $coreBounds Bounds of the galaxy core; generated stars will be placed outside these bounds.
     * @return Collection<PointOfInterest> The created outer-region star points of interest.
     */
    public function generateOuterRegion(Galaxy $galaxy, int $starCount, array $coreBounds): Collection
    {
        $points = $this->generateOuterPoints($galaxy, $starCount, $coreBounds);

        $now = now();
        $version = config('game_config.feature.stamp_version', true) && file_exists(base_path('VERSION'))
            ? trim(file_get_contents(base_path('VERSION')))
            : null;

        // Batch insert for performance
        $batchData = [];
        foreach ($points as $point) {
            $batchData[] = [
                'uuid' => (string) Str::uuid(),
                'galaxy_id' => $galaxy->id,
                'type' => PointOfInterestType::STAR->value,
                'status' => PointOfInterestStatus::ACTIVE->value,
                'x' => $point[0],
                'y' => $point[1],
                'name' => StarNameProvider::generateStarName(),
                'attributes' => json_encode([
                    'stellar_class' => $this->randomStellarClass(),
                    'stellar_size' => $this->randomStellarSize(),
                ]),
                'is_hidden' => false,
                'is_inhabited' => false,
                'region' => RegionType::OUTER->value,
                'is_fortified' => false,
                'version' => $version,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert in chunks of 500
        foreach (array_chunk($batchData, self::CHUNK_SIZE) as $chunk) {
            DB::table('points_of_interest')->insert($chunk);
        }

        // Fetch the created POIs
        return PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('region', RegionType::OUTER)
            ->where('type', PointOfInterestType::STAR)
            ->get();
    }

    /**
     * Generate planetary systems (planets, belts, and moons) for the given outer-region stars and persist them to the points_of_interest table.
     *
     * @param  Collection<PointOfInterest>  $outerStars  Collection of outer-region star POIs to generate systems for
     * @return int Total number of orbital bodies created (planets + moons)
     */
    public function generatePlanetarySystems(Collection $outerStars): int
    {
        $now = now();
        $stars = $outerStars->filter(fn ($poi) => $poi->type === PointOfInterestType::STAR);

        if ($stars->isEmpty()) {
            return 0;
        }

        // Phase 1: Collect all planets and belts in memory
        $planetData = [];
        $moonSpecs = [];  // Track which planets need moons: [temp_key => moon_count]

        foreach ($stars as $star) {
            $planetCount = random_int(5, 12);
            $starPlanets = $this->generatePlanetDataForStar($star, $planetCount, $now);

            foreach ($starPlanets as $spec) {
                $tempKey = count($planetData);
                $planetData[] = $spec['data'];

                if ($spec['moon_count'] > 0) {
                    $moonSpecs[$tempKey] = [
                        'moon_count' => $spec['moon_count'],
                        'planet_name' => $spec['data']['name'],
                        'galaxy_id' => $star->galaxy_id,
                        'x' => $star->x,
                        'y' => $star->y,
                    ];
                }
            }
        }

        // Phase 2: Bulk insert planets in chunks
        $insertedPlanetCount = 0;
        foreach (array_chunk($planetData, self::CHUNK_SIZE) as $chunk) {
            DB::table('points_of_interest')->insert($chunk);
            $insertedPlanetCount += count($chunk);
        }

        // Phase 3: Fetch inserted planets that need moons (by name matching)
        $planetNamesNeedingMoons = array_column(array_values($moonSpecs), 'planet_name');

        if (empty($planetNamesNeedingMoons)) {
            return $insertedPlanetCount;
        }

        $insertedPlanets = PointOfInterest::whereIn('name', $planetNamesNeedingMoons)
            ->where('region', RegionType::OUTER)
            ->get()
            ->keyBy('name');

        // Phase 4: Generate moon data with proper parent IDs
        $moonData = [];
        foreach ($moonSpecs as $spec) {
            $planet = $insertedPlanets->get($spec['planet_name']);
            if (! $planet) {
                continue;
            }

            for ($i = 1; $i <= $spec['moon_count']; $i++) {
                $moonData[] = [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $spec['galaxy_id'],
                    'parent_poi_id' => $planet->id,
                    'orbital_index' => $i,
                    'type' => PointOfInterestType::MOON->value,
                    'status' => PointOfInterestStatus::ACTIVE->value,
                    'x' => $spec['x'],
                    'y' => $spec['y'],
                    'name' => "{$planet->name}-".chr(96 + $i),
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'region' => RegionType::OUTER->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Phase 5: Bulk insert moons
        $insertedMoonCount = 0;
        foreach (array_chunk($moonData, self::CHUNK_SIZE) as $chunk) {
            DB::table('points_of_interest')->insert($chunk);
            $insertedMoonCount += count($chunk);
        }

        return $insertedPlanetCount + $insertedMoonCount;
    }

    /**
     * Build in-memory planet (and optional asteroid belt) specifications for a given star.
     *
     * Generates an array of planet descriptors for insertion: each entry contains the planet's
     * `data` payload matching points_of_interest columns (including orbital_index, type, name,
     * attributes JSON, coordinates, timestamps, etc.) and a `moon_count` hint indicating how
     * many moons should be created for that planet.
     *
     * @param PointOfInterest $star The star POI to host the generated planets; used for galaxy_id, parent_poi_id, coordinates, and naming.
     * @param int $count Number of primary orbital bodies (planets) to generate for the star.
     * @param \DateTimeImmutable|\Carbon\Carbon|string $now Timestamp value to set for created_at and updated_at in each generated row.
     * @return array[] Array of generated entries. Each entry is an associative array with keys:
     *                 - `data` (array): POI column values ready for bulk insert (uuid, galaxy_id, parent_poi_id, orbital_index, type, status, x, y, name, attributes (JSON), is_hidden, is_inhabited, region, created_at, updated_at).
     *                 - `moon_count` (int): number of moons to create for this planet (0 for asteroid belts).
     */
    private function generatePlanetDataForStar(PointOfInterest $star, int $count, $now): array
    {
        $planets = [];
        $planetTypes = [
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::ICE_GIANT,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::OCEAN,
            PointOfInterestType::LAVA,
        ];

        for ($i = 1; $i <= $count; $i++) {
            $type = $planetTypes[array_rand($planetTypes)];

            // Inner planets tend to be rocky, outer planets tend to be gas/ice giants
            if ($i <= 2) {
                $type = random_int(0, 100) < 70
                    ? PointOfInterestType::TERRESTRIAL
                    : PointOfInterestType::LAVA;
            } elseif ($i >= $count - 2) {
                $type = random_int(0, 100) < 60
                    ? PointOfInterestType::ICE_GIANT
                    : PointOfInterestType::GAS_GIANT;
            }

            // Determine moon count
            $moonCount = 0;
            if (in_array($type, [PointOfInterestType::GAS_GIANT, PointOfInterestType::ICE_GIANT])) {
                $moonCount = random_int(2, 6);
            } elseif (random_int(0, 100) < 30) {
                $moonCount = random_int(1, 2);
            }

            $planets[] = [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $star->galaxy_id,
                    'parent_poi_id' => $star->id,
                    'orbital_index' => $i,
                    'type' => $type->value,
                    'status' => PointOfInterestStatus::ACTIVE->value,
                    'x' => $star->x,
                    'y' => $star->y,
                    'name' => "{$star->name} ".$this->romanNumeral($i),
                    'attributes' => json_encode([
                        'orbital_distance' => $i * 10 + random_int(0, 5),
                        'size' => $this->randomPlanetSize($type),
                    ]),
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'region' => RegionType::OUTER->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'moon_count' => $moonCount,
            ];
        }

        // Add asteroid belt (70% chance)
        if (random_int(0, 100) < 70 && $count >= 5) {
            $beltIndex = random_int(3, $count - 2);
            $planets[] = [
                'data' => [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $star->galaxy_id,
                    'parent_poi_id' => $star->id,
                    'orbital_index' => $beltIndex,
                    'type' => PointOfInterestType::ASTEROID_BELT->value,
                    'status' => PointOfInterestStatus::ACTIVE->value,
                    'x' => $star->x,
                    'y' => $star->y,
                    'name' => "{$star->name} Asteroid Belt",
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'region' => RegionType::OUTER->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'moon_count' => 0,
            ];
        }

        return $planets;
    }

    /**
     * Assign randomized mineral deposits to mineable outer-region POIs using chunked bulk updates.
     *
     * Filters the provided collection to non-star POIs, assigns 1–3 randomized deposits (size scaled by a configurable multiplier,
     * with a 95% natural probability to receive deposits), and writes updates in CHUNK_SIZE batches via bulk SQL updates.
     *
     * @param Collection<PointOfInterest> $outerSystems Collection of outer-region POIs (stars and non-stars); only non-star POIs are considered for deposits.
     * @return int Number of POIs that received mineral deposits.
     */
    public function populateMineralDeposits(Collection $outerSystems): int
    {
        $minerals = Mineral::all();
        if ($minerals->isEmpty()) {
            return 0;
        }

        $mineralArray = $minerals->toArray();
        $richnessMultiplier = config('game_config.tiered_galaxy.outer_mineral_multiplier', 2.0);

        // Filter to mineable bodies only
        $mineablePois = $outerSystems->filter(fn ($poi) => $poi->type !== PointOfInterestType::STAR);

        if ($mineablePois->isEmpty()) {
            return 0;
        }

        $depositsCreated = 0;
        $updateBatch = [];

        foreach ($mineablePois as $poi) {
            // 95% chance of mineral deposits in outer region
            if (random_int(0, 100) > 95) {
                continue;
            }

            $deposits = [];
            $depositCount = random_int(1, 3);

            for ($i = 0; $i < $depositCount; $i++) {
                $mineral = $mineralArray[array_rand($mineralArray)];
                $baseSize = random_int(100, 1000);
                $size = (int) ($baseSize * $richnessMultiplier);

                $deposits[$mineral['name']] = [
                    'size' => $size,
                    'richness' => $this->calculateRichness($size),
                    'mineral_id' => $mineral['id'],
                ];
            }

            $updateBatch[] = [
                'id' => $poi->id,
                'mineral_deposits' => json_encode($deposits),
            ];
            $depositsCreated++;

            // Flush batch every 500 records
            if (count($updateBatch) >= self::CHUNK_SIZE) {
                $this->bulkUpdateMineralDeposits($updateBatch);
                $updateBatch = [];
            }
        }

        // Flush remaining
        if (! empty($updateBatch)) {
            $this->bulkUpdateMineralDeposits($updateBatch);
        }

        return $depositsCreated;
    }

    /**
     * Update mineral_deposits for multiple points of interest in bulk.
     *
     * Each batch entry must be an associative array with keys 'id' and 'mineral_deposits' (JSON-encoded string).
     * An empty batch is a no-op.
     *
     * @param array $batch Array of arrays each containing:
     *                     - int    'id'                The POI database id.
     *                     - string 'mineral_deposits' JSON-encoded mineral deposit data.
     */
    private function bulkUpdateMineralDeposits(array $batch): void
    {
        if (empty($batch)) {
            return;
        }

        $ids = array_column($batch, 'id');
        $cases = [];
        $params = [];

        foreach ($batch as $item) {
            $cases[] = 'WHEN id = ? THEN ?';
            $params[] = $item['id'];
            $params[] = $item['mineral_deposits'];
        }

        $params = array_merge($params, $ids);
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));

        DB::statement(
            'UPDATE points_of_interest
             SET mineral_deposits = CASE '.implode(' ', $cases)." END,
                 updated_at = NOW()
             WHERE id IN ({$idPlaceholders})",
            $params
        );
    }

    /**
     * Create dormant warp gates between outer-region stars using a spatial neighborhood heuristic and canonical coordinates.
     *
     * @param Galaxy $galaxy The galaxy in which gates will be created.
     * @param Collection<PointOfInterest> $outerStars Collection of outer-region POIs; only entries with type STAR are considered.
     * @return int The number of dormant warp gates created.
     */
    public function generateDormantGates(Galaxy $galaxy, Collection $outerStars): int
    {
        $stars = $outerStars->filter(fn ($poi) => $poi->type === PointOfInterestType::STAR)->values();

        if ($stars->count() < 2) {
            return 0;
        }

        $maxDistance = config('game_config.tiered_galaxy.outer_gate_max_distance', 200);
        $maxGatesPerStar = 2;

        // Build spatial index
        $cellSize = $maxDistance;
        $spatialIndex = [];

        foreach ($stars as $star) {
            $cellX = (int) floor($star->x / $cellSize);
            $cellY = (int) floor($star->y / $cellSize);
            $key = "{$cellX},{$cellY}";

            if (! isset($spatialIndex[$key])) {
                $spatialIndex[$key] = [];
            }
            $spatialIndex[$key][] = $star;
        }

        // Collect gate pairs using canonical coordinates
        $seen = [];
        $gatePairs = [];
        $now = now();

        foreach ($stars as $star) {
            $cellX = (int) floor($star->x / $cellSize);
            $cellY = (int) floor($star->y / $cellSize);

            // Check 9 neighboring cells
            $candidates = [];
            for ($dx = -1; $dx <= 1; $dx++) {
                for ($dy = -1; $dy <= 1; $dy++) {
                    $key = ($cellX + $dx).','.($cellY + $dy);
                    if (isset($spatialIndex[$key])) {
                        foreach ($spatialIndex[$key] as $candidate) {
                            if ($candidate->id !== $star->id) {
                                $distX = $candidate->x - $star->x;
                                $distY = $candidate->y - $star->y;
                                $distance = sqrt($distX * $distX + $distY * $distY);

                                if ($distance <= $maxDistance) {
                                    $candidates[] = ['poi' => $candidate, 'distance' => $distance];
                                }
                            }
                        }
                    }
                }
            }

            // Sort by distance and take max
            usort($candidates, fn ($a, $b) => $a['distance'] <=> $b['distance']);
            $candidates = array_slice($candidates, 0, $maxGatesPerStar);

            foreach ($candidates as $candidate) {
                $coords = WarpGate::canonicalCoordinates(
                    (int) $star->x,
                    (int) $star->y,
                    (int) $candidate['poi']->x,
                    (int) $candidate['poi']->y
                );

                $key = "{$coords['source_x']},{$coords['source_y']},{$coords['dest_x']},{$coords['dest_y']}";

                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $gatePairs[] = [
                        'uuid' => (string) Str::uuid(),
                        'galaxy_id' => $galaxy->id,
                        'source_poi_id' => $star->id,
                        'destination_poi_id' => $candidate['poi']->id,
                        'source_x' => $coords['source_x'],
                        'source_y' => $coords['source_y'],
                        'dest_x' => $coords['dest_x'],
                        'dest_y' => $coords['dest_y'],
                        'is_hidden' => true,
                        'distance' => $candidate['distance'],
                        'status' => 'dormant',
                        'gate_type' => 'standard',
                        'activation_requirements' => json_encode([
                            'type' => 'sensor_level',
                            'value' => 3,
                            'description' => 'Requires sensor level 3 to activate this dormant gate.',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Bulk insert with deduplication via unique constraint
        $created = 0;
        foreach (array_chunk($gatePairs, self::CHUNK_SIZE) as $chunk) {
            $inserted = DB::table('warp_gates')->insertOrIgnore($chunk);
            $created += $inserted;
        }

        return $created;
    }

    /**
     * Generate coordinate pairs for outer-region points outside the galaxy core.
     *
     * @param Galaxy $galaxy The galaxy whose bounds (width, height) constrain generated coordinates.
     * @param int $count The desired number of points to generate.
     * @param array $coreBounds Associative array with keys `x_min`, `x_max`, `y_min`, `y_max` defining the rectangular core area to exclude.
     * @return array<int, array{0:int,1:int}> An array of `[x, y]` integer coordinate pairs. May contain fewer than `$count` points if a placement limit is reached due to spacing or attempts.
    private function generateOuterPoints(Galaxy $galaxy, int $count, array $coreBounds): array
    {
        $points = [];
        $minSpacing = config('game_config.tiered_galaxy.outer_min_spacing', 25);

        $galaxyWidth = $galaxy->width;
        $galaxyHeight = $galaxy->height;

        $maxAttempts = $count * 20;
        $attempts = 0;

        while (count($points) < $count && $attempts < $maxAttempts) {
            $attempts++;

            $x = random_int(10, $galaxyWidth - 10);
            $y = random_int(10, $galaxyHeight - 10);

            // Skip if inside core bounds
            if ($x >= $coreBounds['x_min'] && $x <= $coreBounds['x_max']
                && $y >= $coreBounds['y_min'] && $y <= $coreBounds['y_max']) {
                continue;
            }

            // Check minimum spacing
            $valid = true;
            foreach ($points as $existing) {
                $dx = $existing[0] - $x;
                $dy = $existing[1] - $y;
                if (sqrt($dx * $dx + $dy * $dy) < $minSpacing) {
                    $valid = false;
                    break;
                }
            }

            if ($valid) {
                $points[] = [$x, $y];
            }
        }

        return $points;
    }

    /**
         * Map a deposit size to a textual richness descriptor.
         *
         * @param float $size Deposit size used to determine richness.
         * @return string One of 'legendary', 'abundant', 'rich', 'moderate', or 'trace' indicating richness for the given size.
         */
    private function calculateRichness(float $size): string
    {
        if ($size >= 1500) {
            return 'legendary';
        }
        if ($size >= 1000) {
            return 'abundant';
        }
        if ($size >= 500) {
            return 'rich';
        }
        if ($size >= 200) {
            return 'moderate';
        }

        return 'trace';
    }

    /**
     * Pick a stellar spectral class using weighted probabilities favoring hotter/larger outer-region stars.
     *
     * @return string One of 'O', 'B', 'A', 'F', 'G', 'K', or 'M', selected according to the method's weight distribution.
     */
    private function randomStellarClass(): string
    {
        $classes = [
            'O' => 5, 'B' => 15, 'A' => 20, 'F' => 20,
            'G' => 20, 'K' => 15, 'M' => 5,
        ];

        $total = array_sum($classes);
        $roll = random_int(1, $total);
        $current = 0;

        foreach ($classes as $class => $weight) {
            $current += $weight;
            if ($roll <= $current) {
                return $class;
            }
        }

        return 'G';
    }

    /**
     * Selects a stellar size key according to weighted probabilities that favor larger outer stars.
     *
     * @return string One of 'dwarf', 'main_sequence', 'subgiant', 'giant', or 'supergiant' representing the chosen stellar size.
     */
    private function randomStellarSize(): string
    {
        $sizes = ['dwarf' => 10, 'main_sequence' => 40, 'subgiant' => 25, 'giant' => 20, 'supergiant' => 5];
        $total = array_sum($sizes);
        $roll = random_int(1, $total);
        $current = 0;

        foreach ($sizes as $size => $weight) {
            $current += $weight;
            if ($roll <= $current) {
                return $size;
            }
        }

        return 'main_sequence';
    }

    /**
     * Map a planet point-of-interest type to a size descriptor used for planet generation.
     *
     * @param PointOfInterestType $type The planet POI type.
     * @return string One of `'massive'`, `'large'`, `'medium'`, or `'small'` representing the planet size category.
     */
    private function randomPlanetSize(PointOfInterestType $type): string
    {
        return match ($type) {
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER => 'massive',
            PointOfInterestType::ICE_GIANT => 'large',
            PointOfInterestType::SUPER_EARTH => 'large',
            PointOfInterestType::TERRESTRIAL, PointOfInterestType::OCEAN, PointOfInterestType::LAVA => 'medium',
            default => 'small',
        };
    }

    /**
         * Convert an integer to its Roman numeral representation for values 1–12.
         *
         * @param int $num The integer to convert.
         * @return string The Roman numeral for integers 1 through 12 (e.g., `1` -> `I`); for other values returns the number as a decimal string.
         */
    private function romanNumeral(int $num): string
    {
        $numerals = [
            1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V',
            6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X',
            11 => 'XI', 12 => 'XII',
        ];

        return $numerals[$num] ?? (string) $num;
    }
}