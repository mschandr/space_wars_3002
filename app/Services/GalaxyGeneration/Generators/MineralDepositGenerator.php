<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\PointOfInterest;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Support\BulkInserter;

/**
 * Generates mineral deposits for planets, moons, and asteroid belts.
 *
 * Outer region has 2x richness multiplier.
 * 95% of outer bodies have deposits.
 */
final class MineralDepositGenerator implements GeneratorInterface
{
    private const DEPOSIT_CHANCE = 95; // Percentage

    private const RICHNESS_MULTIPLIER = 2.0;

    public function getName(): string
    {
        return 'mineral_deposits';
    }

    public function getDependencies(): array
    {
        return [PlanetarySystemGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        // Load minerals (cached for session)
        $minerals = Mineral::all();

        if ($minerals->isEmpty()) {
            return GenerationResult::failure($metrics, 'No minerals found. Run MineralSeeder first.');
        }

        $mineralArray = $minerals->toArray();
        $metrics->setCount('minerals_available', count($mineralArray));

        // Get mineable bodies (not stars)
        $mineableBodies = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('region', RegionType::OUTER)
            ->where('type', '!=', PointOfInterestType::STAR)
            ->get(['id', 'type']);

        $metrics->setCount('mineable_bodies', $mineableBodies->count());

        if ($mineableBodies->isEmpty()) {
            return GenerationResult::success($metrics, ['deposits_created' => 0]);
        }

        // Generate deposits
        $updates = [];

        foreach ($mineableBodies as $body) {
            // 95% chance of deposits
            if (random_int(0, 100) > self::DEPOSIT_CHANCE) {
                continue;
            }

            $deposits = $this->generateDeposits($mineralArray);

            $updates[] = [
                'id' => $body->id,
                'mineral_deposits' => json_encode($deposits),
            ];
        }

        $metrics->setCount('bodies_with_deposits', count($updates));

        // Bulk update
        if (! empty($updates)) {
            BulkInserter::update(
                'points_of_interest',
                $updates,
                'id',
                ['mineral_deposits']
            );
        }

        return GenerationResult::success($metrics, [
            'deposits_created' => count($updates),
            'coverage_percent' => $mineableBodies->count() > 0
                ? round((count($updates) / $mineableBodies->count()) * 100, 1)
                : 0,
        ]);
    }

    /**
     * Generate mineral deposits for a body.
     */
    private function generateDeposits(array $minerals): array
    {
        $deposits = [];
        $depositCount = random_int(1, 3);

        for ($i = 0; $i < $depositCount; $i++) {
            $mineral = $minerals[array_rand($minerals)];
            $baseSize = random_int(100, 1000);
            $size = (int) ($baseSize * self::RICHNESS_MULTIPLIER);

            $deposits[$mineral['name']] = [
                'size' => $size,
                'richness' => $this->calculateRichness($size),
                'mineral_id' => $mineral['id'],
            ];
        }

        return $deposits;
    }

    /**
     * Calculate richness tier from deposit size.
     */
    private function calculateRichness(int $size): string
    {
        return match (true) {
            $size >= 1500 => 'legendary',
            $size >= 1000 => 'abundant',
            $size >= 500 => 'rich',
            $size >= 200 => 'moderate',
            default => 'trace',
        };
    }
}
