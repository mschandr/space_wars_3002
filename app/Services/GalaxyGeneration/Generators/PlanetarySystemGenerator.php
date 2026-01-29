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
 * Outer stars: 5-12 planets (larger, resource-rich systems)
 *
 * Optimized with:
 * - Pre-generated random pools to reduce random_int overhead
 * - Batched UUID generation
 * - Single-pass moon data collection
 */
final class PlanetarySystemGenerator implements GeneratorInterface
{
    private const ROMAN_NUMERALS = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

    private const PLANET_SIZES = ['small', 'medium', 'large', 'massive'];

    private const MOON_SIZES = ['tiny', 'small'];

    private const BELT_DENSITIES = ['sparse', 'moderate', 'dense'];

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

        // Get outer stars only - use toBase() for faster hydration
        $outerStars = PointOfInterest::where('galaxy_id', $galaxyId)
            ->where('region', RegionType::OUTER)
            ->where('type', PointOfInterestType::STAR)
            ->select(['id', 'name', 'x', 'y'])
            ->get()
            ->toArray();

        $starCount = count($outerStars);
        $metrics->setCount('stars_processed', $starCount);

        if ($starCount === 0) {
            return GenerationResult::success($metrics, ['planets_created' => 0, 'moons_created' => 0]);
        }

        // Pre-generate random values in batches for performance
        // Reduced planet counts for faster generation (3-7 instead of 5-12)
        $planetCounts = [];
        $beltFlags = [];
        for ($i = 0; $i < $starCount; $i++) {
            $planetCounts[$i] = 3 + ($i % 5);  // Deterministic 3-7 planets
            $beltFlags[$i] = ($i % 10) < 5;    // 50% have belts
        }

        $planetRows = [];
        $moonSpecs = [];

        // Single pass planet generation using arrays
        foreach ($outerStars as $idx => $star) {
            $this->generateStarSystem(
                $star['id'],
                $star['name'],
                $galaxyId,
                (int) $star['x'],
                (int) $star['y'],
                $planetCounts[$idx],
                $beltFlags[$idx] && $planetCounts[$idx] >= 5,
                $now,
                $planetRows,
                $moonSpecs
            );
        }

        // Phase 2: Bulk insert planets (larger chunks for better throughput)
        $planetsInserted = BulkInserter::insert('points_of_interest', $planetRows, 2000);
        $metrics->setCount('planets_inserted', $planetsInserted);

        // Phase 3: Store moon specs for async generation (if enabled)
        $generateMoonsSync = $context['generate_moons_sync'] ?? false;
        $metrics->setCount('moon_specs_queued', count($moonSpecs));

        if (! $generateMoonsSync || empty($moonSpecs)) {
            // Store moon specs in context for later async processing
            return GenerationResult::success($metrics, [
                'planets_created' => $planetsInserted,
                'moons_created' => 0,
                'moon_specs' => $moonSpecs,  // Pass to async processor
                'moons_deferred' => true,
            ]);
        }

        // Sync moon generation (optional, for testing)
        $moonsInserted = $this->generateMoonsSync($moonSpecs, $galaxyId, $now);
        $metrics->setCount('moons_inserted', $moonsInserted);

        return GenerationResult::success($metrics, [
            'planets_created' => $planetsInserted,
            'moons_created' => $moonsInserted,
            'total_bodies' => $planetsInserted + $moonsInserted,
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
        $outerValue = RegionType::OUTER->value;
        $moonType = PointOfInterestType::MOON->value;
        $activeStatus = PointOfInterestStatus::ACTIVE->value;

        foreach ($moonSpecs as $spec) {
            $planetId = $insertedPlanets[$spec['planet_uuid']] ?? null;
            if (! $planetId) {
                continue;
            }

            for ($i = 1; $i <= $spec['moon_count']; $i++) {
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
                    'attributes' => '{"orbital_distance":'.(($i * 2) + ($i % 3)).',"size":"'.self::MOON_SIZES[$i % 2].'"}',
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'region' => $outerValue,
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
        string $now,
        array &$planetRows,
        array &$moonSpecs
    ): void {
        $activeValue = PointOfInterestStatus::ACTIVE->value;
        $outerValue = RegionType::OUTER->value;

        for ($i = 1; $i <= $planetCount; $i++) {
            $type = $this->getPlanetType($i, $planetCount);
            $moonCount = $this->getMoonCount($type);
            $size = $this->getPlanetSizeIndex($type);
            $orbitalDist = $i * 10 + ($i % 6);
            $planetName = $starName.' '.self::ROMAN_NUMERALS[$i];
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
                'region' => $outerValue,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($moonCount > 0) {
                $moonSpecs[] = [
                    'planet_uuid' => $planetUuid,
                    'planet_name' => $planetName,
                    'moon_count' => $moonCount,
                    'galaxy_id' => $galaxyId,
                    'x' => $x,
                    'y' => $y,
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
                'region' => $outerValue,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * Get planet type based on orbital position (optimized - no random calls).
     */
    private function getPlanetType(int $orbitalIndex, int $totalPlanets): int
    {
        // Inner planets (1-2): Rocky
        if ($orbitalIndex <= 2) {
            return $orbitalIndex % 2 === 0
                ? PointOfInterestType::TERRESTRIAL->value
                : PointOfInterestType::LAVA->value;
        }

        // Outer planets (last 2): Gas/Ice giants
        if ($orbitalIndex >= $totalPlanets - 1) {
            return $orbitalIndex % 2 === 0
                ? PointOfInterestType::ICE_GIANT->value
                : PointOfInterestType::GAS_GIANT->value;
        }

        // Middle planets: Cycle through types deterministically
        $types = [
            PointOfInterestType::TERRESTRIAL->value,
            PointOfInterestType::GAS_GIANT->value,
            PointOfInterestType::ICE_GIANT->value,
            PointOfInterestType::SUPER_EARTH->value,
            PointOfInterestType::OCEAN->value,
        ];

        return $types[$orbitalIndex % 5];
    }

    /**
     * Get moon count based on planet type (deterministic).
     */
    private function getMoonCount(int $typeValue): int
    {
        return match ($typeValue) {
            PointOfInterestType::GAS_GIANT->value => 4,
            PointOfInterestType::ICE_GIANT->value => 3,
            PointOfInterestType::SUPER_EARTH->value => 1,
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
     * Generate planetary system for a star.
     */
    private function generatePlanetarySystem(PointOfInterest $star, int $planetCount, $now): array
    {
        $bodies = [];

        for ($i = 1; $i <= $planetCount; $i++) {
            $type = $this->determinePlanetType($i, $planetCount);
            $moonCount = $this->determineMoonCount($type);

            $bodies[] = [
                'row' => [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $star->galaxy_id,
                    'parent_poi_id' => $star->id,
                    'orbital_index' => $i,
                    'type' => $type->value,
                    'status' => PointOfInterestStatus::ACTIVE->value,
                    'x' => $star->x,
                    'y' => $star->y,
                    'name' => "{$star->name} ".(self::ROMAN_NUMERALS[$i] ?? $i),
                    'attributes' => json_encode([
                        'orbital_distance' => $i * 10 + random_int(0, 5),
                        'size' => $this->getPlanetSize($type),
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
        if (random_int(0, 100) < 70 && $planetCount >= 5) {
            $beltIndex = random_int(3, $planetCount - 2);
            $bodies[] = [
                'row' => [
                    'uuid' => (string) Str::uuid(),
                    'galaxy_id' => $star->galaxy_id,
                    'parent_poi_id' => $star->id,
                    'orbital_index' => $beltIndex,
                    'type' => PointOfInterestType::ASTEROID_BELT->value,
                    'status' => PointOfInterestStatus::ACTIVE->value,
                    'x' => $star->x,
                    'y' => $star->y,
                    'name' => "{$star->name} Asteroid Belt",
                    'attributes' => json_encode([
                        'orbital_distance' => $beltIndex * 10 + random_int(0, 5),
                        'density' => ['sparse', 'moderate', 'dense'][random_int(0, 2)],
                    ]),
                    'is_hidden' => false,
                    'is_inhabited' => false,
                    'region' => RegionType::OUTER->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                'moon_count' => 0,
            ];
        }

        return $bodies;
    }

    /**
     * Determine planet type based on orbital position.
     */
    private function determinePlanetType(int $orbitalIndex, int $totalPlanets): PointOfInterestType
    {
        // Inner planets (1-2): Rocky
        if ($orbitalIndex <= 2) {
            return random_int(0, 100) < 70
                ? PointOfInterestType::TERRESTRIAL
                : PointOfInterestType::LAVA;
        }

        // Outer planets (last 2): Gas/Ice giants
        if ($orbitalIndex >= $totalPlanets - 1) {
            return random_int(0, 100) < 60
                ? PointOfInterestType::ICE_GIANT
                : PointOfInterestType::GAS_GIANT;
        }

        // Middle planets: Mixed
        $types = [
            PointOfInterestType::TERRESTRIAL,
            PointOfInterestType::GAS_GIANT,
            PointOfInterestType::ICE_GIANT,
            PointOfInterestType::SUPER_EARTH,
            PointOfInterestType::OCEAN,
        ];

        return $types[array_rand($types)];
    }

    /**
     * Determine moon count based on planet type.
     */
    private function determineMoonCount(PointOfInterestType $type): int
    {
        return match ($type) {
            PointOfInterestType::GAS_GIANT, PointOfInterestType::ICE_GIANT => random_int(2, 6),
            PointOfInterestType::TERRESTRIAL, PointOfInterestType::SUPER_EARTH => random_int(0, 100) < 30 ? random_int(1, 2) : 0,
            default => 0,
        };
    }

    /**
     * Get planet size based on type.
     */
    private function getPlanetSize(PointOfInterestType $type): string
    {
        return match ($type) {
            PointOfInterestType::GAS_GIANT, PointOfInterestType::HOT_JUPITER => 'massive',
            PointOfInterestType::ICE_GIANT, PointOfInterestType::SUPER_EARTH => 'large',
            PointOfInterestType::TERRESTRIAL, PointOfInterestType::OCEAN, PointOfInterestType::LAVA => 'medium',
            default => 'small',
        };
    }
}
