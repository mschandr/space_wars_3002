<?php

namespace App\Services;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Faker\Providers\StarNameProvider;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates core region systems for tiered galaxies.
 *
 * Core systems are probabilistically:
 * - 90% charted, of those 90% populated → ~81% inhabited
 * - Inhabited systems get fortress defenses and trading posts
 * - Connected via active warp gates
 */
class CoreSystemGenerator
{
    private SystemDefenseFactory $defenseFactory;

    public function __construct(SystemDefenseFactory $defenseFactory)
    {
        $this->defenseFactory = $defenseFactory;
    }

    /**
     * Generate the core region for a tiered galaxy.
     *
     * @param  Galaxy  $galaxy  The galaxy to populate
     * @param  int  $starCount  Number of stars to generate
     * @param  array  $coreBounds  Core region bounds {x_min, x_max, y_min, y_max}
     * @return Collection<PointOfInterest> Created POIs
     */
    public function generateCoreRegion(Galaxy $galaxy, int $starCount, array $coreBounds): Collection
    {
        $points = $this->generateCorePoints($starCount, $coreBounds);
        $pois = collect();

        $now = now();
        $version = config('game_config.feature.stamp_version', true) && file_exists(base_path('VERSION'))
            ? trim(file_get_contents(base_path('VERSION')))
            : null;

        $chartedPct = RegionType::CORE->getChartedPercentage();
        $inhabitedPct = RegionType::CORE->getInhabitedPercentage();

        // Batch insert for performance
        $batchData = [];
        foreach ($points as $point) {
            $isCharted = (random_int(1, 10000) / 10000) <= $chartedPct;
            $isInhabited = $isCharted && $chartedPct > 0
                && (random_int(1, 10000) / 10000) <= ($inhabitedPct / $chartedPct);

            $batchData[] = [
                'uuid' => (string) Str::uuid(),
                'galaxy_id' => $galaxy->id,
                'type' => PointOfInterestType::STAR->value,
                'status' => PointOfInterestStatus::ACTIVE->value,
                'x' => $point[0],
                'y' => $point[1],
                'name' => StarNameProvider::generateStarName(),
                'attributes' => json_encode(['stellar_class' => $this->randomStellarClass()]),
                'is_hidden' => false,
                'is_inhabited' => $isInhabited,
                'is_charted' => $isCharted,
                'region' => RegionType::CORE->value,
                'is_fortified' => false,  // Will be set when defenses are deployed
                'version' => $version,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Insert in chunks
        $chunks = array_chunk($batchData, 100);
        foreach ($chunks as $chunk) {
            DB::table('points_of_interest')->insert($chunk);
        }

        // Fetch the created POIs
        $pois = PointOfInterest::where('galaxy_id', $galaxy->id)
            ->where('region', RegionType::CORE)
            ->get();

        return $pois;
    }

    /**
     * Deploy fortress defenses at all core systems.
     *
     * @param  Collection<PointOfInterest>  $coreSystems  Core region POIs
     * @return int Number of systems fortified
     */
    public function deployFortressDefenses(Collection $coreSystems): int
    {
        $fortified = 0;

        foreach ($coreSystems as $poi) {
            if ($poi->type !== PointOfInterestType::STAR) {
                continue;
            }

            if (! $poi->is_inhabited) {
                continue;
            }

            $this->defenseFactory->deployFortressDefenses($poi, 1);
            $fortified++;
        }

        return $fortified;
    }

    /**
     * Create trading posts at core systems.
     * Premium hubs with all services: shipyard, salvage, upgrades.
     *
     * @param  Collection<PointOfInterest>  $coreSystems  Core region POIs
     * @return int Number of trading posts created
     */
    public function createTradingPosts(Collection $coreSystems): int
    {
        $created = 0;

        foreach ($coreSystems as $poi) {
            if ($poi->type !== PointOfInterestType::STAR) {
                continue;
            }

            if (! $poi->is_inhabited) {
                continue;
            }

            $this->createPremiumTradingHub($poi);
            $created++;
        }

        return $created;
    }

    /**
     * Create a premium trading hub with all services.
     */
    private function createPremiumTradingHub(PointOfInterest $poi): TradingHub
    {
        // Generate hub name
        $hubName = $this->generateHubName($poi);

        return TradingHub::create([
            'uuid' => Str::uuid(),
            'poi_id' => $poi->id,
            'name' => $hubName,
            'hub_type' => 'trading_post',
            'is_active' => true,
            'has_ship_shop' => true,      // Shipyard
            'has_repair_shop' => true,    // Salvage/repairs
            'has_component_shop' => true, // Upgrades
            'has_plans_shop' => true,     // Rare plans
            'tax_rate' => 0.05,           // 5% tax (core systems are well-regulated)
            'attributes' => [
                'region' => 'core',
                'premium' => true,
                'services' => ['shipyard', 'salvage', 'upgrades', 'plans', 'cartography'],
            ],
        ]);
    }

    /**
     * Generate points within the core bounds using quasi-random distribution.
     */
    private function generateCorePoints(int $count, array $bounds): array
    {
        $points = [];
        $minSpacing = config('game_config.tiered_galaxy.core_min_spacing', 15);

        $width = $bounds['x_max'] - $bounds['x_min'];
        $height = $bounds['y_max'] - $bounds['y_min'];

        // Use golden ratio-based distribution for even spacing
        $goldenRatio = (1 + sqrt(5)) / 2;
        $angleIncrement = 2 * M_PI / ($goldenRatio * $goldenRatio);

        $maxAttempts = $count * 10;
        $attempts = 0;

        while (count($points) < $count && $attempts < $maxAttempts) {
            $attempts++;

            // Spiral distribution from center
            $i = count($points);
            $radius = sqrt($i / $count) * (min($width, $height) / 2 - $minSpacing);
            $angle = $i * $angleIncrement;

            $x = $bounds['x_min'] + $width / 2 + $radius * cos($angle);
            $y = $bounds['y_min'] + $height / 2 + $radius * sin($angle);

            // Add some jitter
            $x += (random_int(-100, 100) / 100) * $minSpacing * 0.5;
            $y += (random_int(-100, 100) / 100) * $minSpacing * 0.5;

            // Clamp to bounds
            $x = max($bounds['x_min'] + 5, min($bounds['x_max'] - 5, $x));
            $y = max($bounds['y_min'] + 5, min($bounds['y_max'] - 5, $y));

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
                $points[] = [(int) round($x), (int) round($y)];
            }
        }

        return $points;
    }

    /**
     * Generate a trading hub name.
     */
    private function generateHubName(PointOfInterest $poi): string
    {
        $prefixes = ['Central', 'Prime', 'Grand', 'Imperial', 'Federal', 'Core'];
        $suffixes = ['Trading Post', 'Commerce Hub', 'Market Station', 'Exchange', 'Emporium'];

        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = $suffixes[array_rand($suffixes)];

        return "{$prefix} {$poi->name} {$suffix}";
    }

    /**
     * Generate a random stellar class weighted toward hospitable stars.
     */
    private function randomStellarClass(): string
    {
        $classes = [
            'G' => 40,  // Sun-like, most hospitable
            'K' => 30,  // Orange dwarfs
            'F' => 15,  // Yellow-white
            'M' => 10,  // Red dwarfs
            'A' => 5,   // Blue-white
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
}
