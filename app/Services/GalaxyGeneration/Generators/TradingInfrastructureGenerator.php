<?php

namespace App\Services\GalaxyGeneration\Generators;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Services\GalaxyGeneration\Contracts\GeneratorInterface;
use App\Services\GalaxyGeneration\Data\GenerationMetrics;
use App\Services\GalaxyGeneration\Data\GenerationResult;
use App\Services\GalaxyGeneration\Support\BulkInserter;
use Illuminate\Support\Str;

/**
 * Generates trading infrastructure (hubs, shops) for inhabited systems.
 *
 * Core systems: 100% have premium trading hubs with all services
 * Uses bulk insert for performance.
 */
final class TradingInfrastructureGenerator implements GeneratorInterface
{
    private const HUB_PREFIXES = ['Central', 'Prime', 'Grand', 'Imperial', 'Federal', 'Core'];

    private const HUB_SUFFIXES = ['Trading Post', 'Commerce Hub', 'Market Station', 'Exchange', 'Emporium'];

    public function getName(): string
    {
        return 'trading_infrastructure';
    }

    public function getDependencies(): array
    {
        return [StarFieldGenerator::class, WarpGateNetworkGenerator::class];
    }

    public function generate(Galaxy $galaxy, array $context = []): GenerationResult
    {
        $metrics = new GenerationMetrics;

        // Get core (inhabited) stars
        $coreStars = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('region', RegionType::CORE)
            ->where('type', PointOfInterestType::STAR)
            ->where('is_inhabited', true)
            ->get(['id', 'name']);

        $metrics->setCount('core_stars', $coreStars->count());

        if ($coreStars->isEmpty()) {
            return GenerationResult::success($metrics, ['hubs_created' => 0]);
        }

        // Build trading hub rows
        $now = now();
        $hubRows = [];

        foreach ($coreStars as $star) {
            $hubRows[] = [
                'uuid' => (string) Str::uuid(),
                'poi_id' => $star->id,
                'name' => $this->generateHubName($star->name),
                'type' => 'trading_post',
                'is_active' => true,
                'has_salvage_yard' => true,
                'has_plans' => true,
                'gate_count' => 0,
                'tax_rate' => 0.05, // 5% tax for core systems
                'services' => json_encode(['shipyard', 'salvage', 'upgrades', 'plans', 'cartography']),
                'attributes' => json_encode([
                    'region' => 'core',
                    'premium' => true,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert
        $inserted = BulkInserter::insert('trading_hubs', $hubRows);

        $metrics->setCount('hubs_created', $inserted);

        return GenerationResult::success($metrics, [
            'hubs_created' => $inserted,
        ]);
    }

    /**
     * Generate a trading hub name.
     */
    private function generateHubName(string $starName): string
    {
        $prefix = self::HUB_PREFIXES[array_rand(self::HUB_PREFIXES)];
        $suffix = self::HUB_SUFFIXES[array_rand(self::HUB_SUFFIXES)];

        return "{$prefix} {$starName} {$suffix}";
    }
}
