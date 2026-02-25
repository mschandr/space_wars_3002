<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Support\BulkInserter;
use Illuminate\Support\Str;

/**
 * Generates planetary systems (planets, moons, asteroid belts) for stars.
 *
 * Planet counts and types are driven by stellar classification and size.
 * Moons are generated synchronously per chunk.
 *
 * Optimized with:
 * - Pre-generated random pools to reduce random_int overhead
 * - Batched UUID generation
 * - Single-pass moon data collection
 */
final class PlanetarySystemGenerator implements GeneratorInterface
{
    private const ROMAN_NUMERALS = [
        '', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X',
        'XI', 'XII', 'XIII', 'XIV', 'XV', 'XVI', 'XVII', 'XVIII', 'XIX', 'XX',
    ];

    private const PLANET_SIZES = ['small', 'medium', 'large', 'massive'];

    private const MOON_SIZES = ['tiny', 'small', 'medium', 'large'];

    private const BELT_DENSITIES = ['sparse', 'moderate', 'dense'];

    private const MOON_TYPES = ['rocky', 'icy', 'volcanic', 'habitable', 'forest'];

    /**
     * Base planet count ranges per stellar class [min, max].
     */
    private const PLANET_COUNT_RANGES = [
        'O' => [0, 2],
        'B' => [0, 3],
        'A' => [1, 5],
        'F' => [2, 8],
        'G' => [3, 10],
        'K' => [2, 7],
        'M' => [1, 6],
    ];

    /**
     * Max planet count modifier per stellar size.
     */
    private const SIZE_MODIFIERS = [
        'dwarf' => -2,
        'main_sequence' => 0,
        'subgiant' => 1,
        'giant' => 2,
        'supergiant' => 3,
    ];

    /**
     * Asteroid belt chance per stellar class.
     */
    private const ASTEROID_BELT_CHANCES = [
        'O' => 0.10,
        'B' => 0.15,
        'A' => 0.25,
        'F' => 0.35,
        'G' => 0.40,
        'K' => 0.30,
        'M' => 0.20,
    ];

    public function getName(): string
    {
        return 'planetary_systems';
    }

    public function getDependencies(): array
    {
        return [StarFieldGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;
        $now = now()->format('Y-m-d H:i:s');
        $galaxyId = $galaxy->id;

        // Get stars that need planetary systems:
        // 1. All outer region stars (frontier exploration)
        // 2. All inhabited stars (player spawn locations need planets)
        // 3. All charted core stars (discoverable systems need content)
        // Use chunked processing to reduce memory usage
        $starQuery = PointOfInterest::where('galaxy_id', $galaxyId)
            ->where('type', PointOfInterestType::STAR)
            ->where(function ($query) {
                $query->where('region', RegionType::OUTER)
                    ->orWhere('is_inhabited', true)
                    ->orWhere('is_charted', true);
            })
            ->select(['id', 'name', 'x', 'y', 'region', 'attributes']);

        $starCount = $starQuery->count();
        $metrics->setCount('stars_processed', $starCount);

        if ($starCount === 0) {
            return GenerationResult::success($metrics, ['planets_created' => 0, 'moons_created' => 0]);
        }

        $planetsInserted = 0;
        $moonsInserted = 0;
        $totalMoonSpecs = 0;
        $chunkSize = 100; // Process 100 stars at a time for better memory management

        // Process stars in chunks to limit memory usage
        $starQuery->orderBy('id')->chunk($chunkSize, function ($stars) use (
            &$planetsInserted, &$moonsInserted, &$totalMoonSpecs,
            $galaxyId, $now
        ) {
            $planetRows = [];
            $moonSpecs = [];

            foreach ($stars as $star) {
                $attrs = $star->attributes ?? [];
                $stellarClass = $attrs['stellar_class'] ?? 'G';
                $stellarSize = $attrs['stellar_size'] ?? 'main_sequence';

                $planetCount = $this->getPlanetCountForStar($stellarClass, $stellarSize);
                $addBelt = $planetCount >= 4
                    && (random_int(1, 100) / 100) <= $this->getAsteroidBeltChance($stellarClass);

                // Handle region as enum or string
                $region = $star->region;
                if ($region instanceof RegionType) {
                    $region = $region->value;
                }

                $this->generateStarSystem(
                    $star->id,
                    $star->name,
                    $galaxyId,
                    (int) $star->x,
                    (int) $star->y,
                    $planetCount,
                    $addBelt,
                    $stellarClass,
                    $region ?? RegionType::OUTER->value,
                    $now,
                    $planetRows,
                    $moonSpecs
                );
            }

            // Insert this chunk immediately
            if (! empty($planetRows)) {
                $planetsInserted += BulkInserter::insert('points_of_interest', $planetRows, 500);
            }

            // Generate moons for this chunk's planets
            if (! empty($moonSpecs)) {
                $moonsInserted += $this->generateMoonsSync($moonSpecs, $galaxyId, $now);
            }
            $totalMoonSpecs += count($moonSpecs);

            // Free memory for this chunk
            unset($planetRows, $moonSpecs);

            // Force garbage collection after each chunk
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        });

        $metrics->setCount('planets_inserted', $planetsInserted);
        $metrics->setCount('moons_inserted', $moonsInserted);
        $metrics->setCount('moon_specs_queued', $totalMoonSpecs);

        return GenerationResult::success($metrics, [
            'planets_created' => $planetsInserted,
            'moons_created' => $moonsInserted,
            'moons_deferred' => false,
        ]);
    }

    /**
     * Generate moons synchronously (for testing or small galaxies).
     */
    private function generateMoonsSync(array $moonSpecs, int $galaxyId, string $now): int
    {
        if (empty($moonSpecs)) {
            return 0;
        }

        // Fetch planets that need moons
        $planetUuids = array_column($moonSpecs, 'planet_uuid');
        $insertedPlanets = [];

        foreach (array_chunk($planetUuids, 2000) as $uuidChunk) {
            $results = PointOfInterest::whereIn('uuid', $uuidChunk)->pluck('id', 'uuid');
            foreach ($results as $uuid => $id) {
                $insertedPlanets[$uuid] = $id;
            }
        }

        // Generate moon rows
        $moonRows = [];
        $moonType = PointOfInterestType::MOON->value;
        $activeStatus = PointOfInterestStatus::ACTIVE->value;

        foreach ($moonSpecs as $spec) {
            $planetId = $insertedPlanets[$spec['planet_uuid']] ?? null;
            if (! $planetId) {
                continue;
            }

            $specRegion = $spec['region'] ?? RegionType::OUTER->value;
            $parentType = $spec['parent_planet_type'] ?? PointOfInterestType::TERRESTRIAL->value;

            for ($i = 1; $i <= $spec['moon_count']; $i++) {
                $size = $this->getMoonSize($parentType);
                $moonTypeStr = $this->getMoonType($parentType, $i, $spec['moon_count']);
                $habitability = $this->getMoonHabitability($moonTypeStr, $size);

                $moonRows[] = [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $spec['galaxy_id'],
                    'parent_poi_id' => $planetId,
                    'orbital_index' => $i,
                    'type' => $moonType,
                    'status' => $activeStatus,
                    'x' => $spec['x'],
                    'y' => $spec['y'],
                    'name' => $spec['planet_name'].'-'.chr(96 + $i),
                    'attributes' => json_encode([
                        'orbital_distance' => ($i * 2) + ($i % 3),
                        'size' => $size,
                        'moon_type' => $moonTypeStr,
                        'habitability_score' => $habitability,
                        'habitable' => $habitability > 0.0,
                    ]),
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'is_charted' => false,
                    'region' => $specRegion,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        return BulkInserter::insert('points_of_interest', $moonRows, 2000);
    }

    /**
     * Generate a star system directly into arrays (no intermediate objects).
     */
    private function generateStarSystem(
        int $starId,
        string $starName,
        int $galaxyId,
        int $x,
        int $y,
        int $planetCount,
        bool $addBelt,
        string $stellarClass,
        string $region,
        string $now,
        array &$planetRows,
        array &$moonSpecs
    ): void {
        $activeValue = PointOfInterestStatus::ACTIVE->value;
        $regionValue = is_string($region) ? $region : RegionType::OUTER->value;

        for ($i = 1; $i <= $planetCount; $i++) {
            $type = $this->getPlanetTypeForStar($i, $planetCount, $stellarClass);
            $moonCount = $this->getMoonCount($type);
            $size = $this->getPlanetSizeIndex($type);
            $orbitalDist = $i * 10 + ($i % 6);
            $planetName = $starName.' '.(self::ROMAN_NUMERALS[$i] ?? (string) $i);
            $planetUuid = (string) Str::uuid();

            $planetRows[] = [
                'uuid' => $planetUuid,
                'galaxy_id' => $galaxyId,
                'parent_poi_id' => $starId,
                'orbital_index' => $i,
                'type' => $type,
                'status' => $activeValue,
                'x' => $x,
                'y' => $y,
                'name' => $planetName,
                'attributes' => '{"orbital_distance":'.$orbitalDist.',"size":"'.self::PLANET_SIZES[$size].'"}',
                'is_hidden' => false,
                'is_inhabited' => false,
                'is_charted' => false,
                'region' => $regionValue,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($moonCount > 0) {
                $moonSpecs[] = [
                    'planet_uuid' => $planetUuid,
                    'planet_name' => $planetName,
                    'moon_count' => $moonCount,
                    'parent_planet_type' => $type,
                    'galaxy_id' => $galaxyId,
                    'x' => $x,
                    'y' => $y,
                    'region' => $regionValue,
                ];
            }
        }

        // Add asteroid belt
        if ($addBelt) {
            $beltIndex = 3 + ($planetCount % 4);
            $planetRows[] = [
                'uuid' => (string) Str::uuid(),
                'galaxy_id' => $galaxyId,
                'parent_poi_id' => $starId,
                'orbital_index' => $beltIndex,
                'type' => PointOfInterestType::ASTEROID_BELT->value,
                'status' => $activeValue,
                'x' => $x,
                'y' => $y,
                'name' => $starName.' Asteroid Belt',
                'attributes' => '{"orbital_distance":'.($beltIndex * 10).',"density":"'.self::BELT_DENSITIES[$beltIndex % 3].'"}',
                'is_hidden' => false,
                'is_inhabited' => false,
                'is_charted' => false,
                'region' => $regionValue,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * Get planet count based on stellar class and size.
     */
    private function getPlanetCountForStar(string $stellarClass, string $stellarSize): int
    {
        [$min, $max] = self::PLANET_COUNT_RANGES[$stellarClass] ?? self::PLANET_COUNT_RANGES['G'];
        $modifier = self::SIZE_MODIFIERS[$stellarSize] ?? 0;

        $adjustedMax = max($min, $max + $modifier);

        return random_int($min, $adjustedMax);
    }

    /**
     * Get asteroid belt chance for a stellar class.
     */
    private function getAsteroidBeltChance(string $stellarClass): float
    {
        return self::ASTEROID_BELT_CHANCES[$stellarClass] ?? 0.30;
    }

    /**
     * Get planet type based on stellar class and orbital position.
     *
     * Uses cumulative weight arrays with random_int() for bulk performance
     * instead of WeightedRandomGenerator.
     */
    private function getPlanetTypeForStar(int $orbitalIndex, int $totalPlanets, string $stellarClass): int
    {
        // Calculate normalized orbital distance (0 = inner, 1 = outer)
        $normalizedDistance = ($totalPlanets > 1)
            ? ($orbitalIndex - 1) / ($totalPlanets - 1)
            : 0.5;

        if ($normalizedDistance < 0.4) {
            return $this->selectInnerPlanetType($stellarClass);
        }

        return $this->selectOuterPlanetType($normalizedDistance);
    }

    /**
     * Select inner planet type based on stellar class using weighted random.
     */
    private function selectInnerPlanetType(string $stellarClass): int
    {
        $weights = match ($stellarClass) {
            'O', 'B' => [
                PointOfInterestType::LAVA->value => 60,
                PointOfInterestType::CHTHONIC->value => 30,
                PointOfInterestType::TERRESTRIAL->value => 10,
            ],
            'A', 'F' => [
                PointOfInterestType::TERRESTRIAL->value => 40,
                PointOfInterestType::LAVA->value => 30,
                PointOfInterestType::SUPER_EARTH->value => 20,
                PointOfInterestType::OCEAN->value => 10,
            ],
            'G' => [
                PointOfInterestType::TERRESTRIAL->value => 45,
                PointOfInterestType::SUPER_EARTH->value => 25,
                PointOfInterestType::OCEAN->value => 15,
                PointOfInterestType::LAVA->value => 15,
            ],
            'K' => [
                PointOfInterestType::TERRESTRIAL->value => 50,
                PointOfInterestType::SUPER_EARTH->value => 30,
                PointOfInterestType::OCEAN->value => 20,
            ],
            'M' => [
                PointOfInterestType::TERRESTRIAL->value => 60,
                PointOfInterestType::SUPER_EARTH->value => 25,
                PointOfInterestType::LAVA->value => 15,
            ],
            default => [
                PointOfInterestType::TERRESTRIAL->value => 45,
                PointOfInterestType::SUPER_EARTH->value => 25,
                PointOfInterestType::OCEAN->value => 15,
                PointOfInterestType::LAVA->value => 15,
            ],
        };

        return $this->weightedPick($weights);
    }

    /**
     * Select outer planet type based on normalized orbital distance.
     */
    private function selectOuterPlanetType(float $normalizedDistance): int
    {
        if ($normalizedDistance > 0.7) {
            // Very outer: favors ice giants
            $weights = [
                PointOfInterestType::ICE_GIANT->value => 60,
                PointOfInterestType::GAS_GIANT->value => 30,
                PointOfInterestType::DWARF_PLANET->value => 10,
            ];
        } else {
            // Mid-outer: favors gas giants
            $weights = [
                PointOfInterestType::GAS_GIANT->value => 70,
                PointOfInterestType::ICE_GIANT->value => 25,
                PointOfInterestType::SUPER_EARTH->value => 5,
            ];
        }

        return $this->weightedPick($weights);
    }

    /**
     * Pick a value from a weighted array using cumulative weights.
     *
     * @param  array<int, int>  $weights  Map of value => weight
     */
    private function weightedPick(array $weights): int
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        // Fallback (should not reach)
        return array_key_first($weights);
    }

    /**
     * Get moon count based on planet type with randomized ranges.
     */
    private function getMoonCount(int $typeValue): int
    {
        return match ($typeValue) {
            PointOfInterestType::GAS_GIANT->value => random_int(3, 8),
            PointOfInterestType::ICE_GIANT->value => random_int(2, 6),
            PointOfInterestType::SUPER_EARTH->value => random_int(0, 3),
            PointOfInterestType::TERRESTRIAL->value => random_int(0, 2),
            PointOfInterestType::OCEAN->value => random_int(0, 2),
            PointOfInterestType::LAVA->value => random_int(0, 1),
            PointOfInterestType::CHTHONIC->value => random_int(0, 2),
            PointOfInterestType::DWARF_PLANET->value => random_int(0, 1),
            default => 0,
        };
    }

    /**
     * Get planet size index.
     */
    private function getPlanetSizeIndex(int $typeValue): int
    {
        return match ($typeValue) {
            PointOfInterestType::GAS_GIANT->value => 3, // massive
            PointOfInterestType::ICE_GIANT->value, PointOfInterestType::SUPER_EARTH->value => 2, // large
            PointOfInterestType::TERRESTRIAL->value, PointOfInterestType::OCEAN->value, PointOfInterestType::LAVA->value => 1, // medium
            default => 0, // small
        };
    }

    /**
     * Get moon size weighted by parent planet type.
     */
    private function getMoonSize(int $parentType): string
    {
        $weights = match ($parentType) {
            PointOfInterestType::GAS_GIANT->value, PointOfInterestType::HOT_JUPITER->value => [
                'tiny' => 10, 'small' => 30, 'medium' => 40, 'large' => 20,
            ],
            PointOfInterestType::ICE_GIANT->value => [
                'tiny' => 20, 'small' => 40, 'medium' => 30, 'large' => 10,
            ],
            PointOfInterestType::SUPER_EARTH->value, PointOfInterestType::OCEAN->value => [
                'tiny' => 40, 'small' => 45, 'medium' => 15,
            ],
            PointOfInterestType::CHTHONIC->value, PointOfInterestType::LAVA->value => [
                'tiny' => 50, 'small' => 40, 'medium' => 10,
            ],
            default => [
                'tiny' => 60, 'small' => 35, 'medium' => 5,
            ],
        };

        return $this->weightedPickString($weights);
    }

    /**
     * Get moon type based on parent planet type and orbital position.
     */
    private function getMoonType(int $parentType, int $orbitalIndex, int $moonCount): string
    {
        if ($parentType === PointOfInterestType::GAS_GIANT->value || $parentType === PointOfInterestType::HOT_JUPITER->value) {
            $normalizedPos = $moonCount > 1 ? ($orbitalIndex - 1) / ($moonCount - 1) : 0.5;

            if ($normalizedPos < 0.33) {
                // Inner moons
                $weights = ['volcanic' => 50, 'rocky' => 40, 'icy' => 10];
            } elseif ($normalizedPos < 0.66) {
                // Mid moons
                $weights = ['rocky' => 50, 'icy' => 25, 'volcanic' => 15, 'habitable' => 5, 'forest' => 5];
            } else {
                // Outer moons
                $weights = ['icy' => 70, 'rocky' => 25, 'habitable' => 3, 'forest' => 2];
            }

            return $this->weightedPickString($weights);
        }

        if ($parentType === PointOfInterestType::ICE_GIANT->value) {
            return $this->weightedPickString([
                'icy' => 60, 'rocky' => 35, 'habitable' => 3, 'forest' => 2,
            ]);
        }

        if ($parentType === PointOfInterestType::OCEAN->value) {
            return $this->weightedPickString([
                'icy' => 50, 'rocky' => 35, 'habitable' => 10, 'forest' => 5,
            ]);
        }

        if ($parentType === PointOfInterestType::LAVA->value || $parentType === PointOfInterestType::CHTHONIC->value) {
            return $this->weightedPickString([
                'rocky' => 55, 'volcanic' => 40, 'icy' => 5,
            ]);
        }

        // Terrestrial, Super Earth, Dwarf Planet, and others
        return $this->weightedPickString([
            'rocky' => 70, 'icy' => 29, 'habitable' => 1,
        ]);
    }

    /**
     * Get moon habitability score based on type and size.
     * Only habitable/forest moons that are medium or large get a score.
     */
    private function getMoonHabitability(string $moonType, string $size): float
    {
        if (! in_array($moonType, ['habitable', 'forest'])) {
            return 0.0;
        }

        if (! in_array($size, ['medium', 'large'])) {
            return 0.0;
        }

        return round(random_int(40, 70) / 100, 2);
    }

    /**
     * Pick a string value from a weighted array using cumulative weights.
     *
     * @param  array<string, int>  $weights  Map of string => weight
     */
    private function weightedPickString(array $weights): string
    {
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $cumulative = 0;

        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
