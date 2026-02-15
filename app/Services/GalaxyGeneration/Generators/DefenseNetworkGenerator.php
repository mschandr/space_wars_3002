<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Defense\SystemDefenseType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Support\BulkInserter;
use Illuminate\Support\Str;

/**
 * Generates defense networks for inhabited systems.
 *
 * Each inhabited system gets a fortress defense package:
 * - 4 Orbital Cannons
 * - 2 Space Lasers
 * - 6 Ground Missiles
 * - 1 Planetary Shield
 * - 1 Fighter Port
 */
final class DefenseNetworkGenerator implements GeneratorInterface
{
    /**
     * Get the fortress defense package (type => quantity).
     */
    private function getFortressPackage(): array
    {
        return [
            SystemDefenseType::ORBITAL_CANNON,
            SystemDefenseType::SPACE_LASER,
            SystemDefenseType::GROUND_MISSILE,
            SystemDefenseType::PLANETARY_SHIELD,
            SystemDefenseType::FIGHTER_PORT,
        ];
    }

    public function getName(): string
    {
        return 'defense_network';
    }

    public function getDependencies(): array
    {
        return [StarFieldGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        // Get inhabited stars (any region)
        $coreStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('is_inhabited', true)
            ->where('type', PointOfInterestType::STAR)
            ->get(['id']);

        $metrics->setCount('inhabited_stars', $coreStars->count());

        if ($coreStars->isEmpty()) {
            return GenerationResult::success($metrics, ['defenses_created' => 0]);
        }

        // Build defense rows
        $now = now();
        $defenseRows = [];
        $fortressTypes = $this->getFortressPackage();

        foreach ($coreStars as $star) {
            foreach ($fortressTypes as $type) {
                $baseHealth = $type->getBaseHealth();
                $defenseRows[] = [
                    'uuid' => (string) Str::uuid(),
                    'poi_id' => $star->id,
                    'defense_type' => $type->value,
                    'level' => 1,
                    'quantity' => $type->getFortressQuantity(),
                    'health' => $baseHealth,
                    'max_health' => $baseHealth,
                    'is_active' => true,
                    'attributes' => json_encode($type->getDefaultAttributes()),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Bulk insert
        $inserted = BulkInserter::insert('system_defenses', $defenseRows);

        $metrics->setCount('defenses_created', $inserted);
        $metrics->setCount('systems_fortified', $coreStars->count());

        // Mark inhabited POIs as fortified
        PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('is_inhabited', true)
            ->where('type', PointOfInterestType::STAR)
            ->update(['is_fortified' => true]);

        return GenerationResult::success($metrics, [
            'defenses_created' => $inserted,
            'systems_fortified' => $coreStars->count(),
        ]);
    }
}
