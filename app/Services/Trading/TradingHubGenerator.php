<?php

namespace App\Services\Trading;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\Plan;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TradingHubGenerator
{
    private int $minGatesForHub;

    private float $salvageYardProbability;

    private float $plansProbability;

    private float $hubSpawnProbability;

    private int $minHubDistance;

    public function __construct(
        int $minGatesForHub = 1,           // Minimum gates for inhabited systems
        float $salvageYardProbability = 0.3,
        float $plansProbability = 0.05,
        float $hubSpawnProbability = 0.65, // 65% of inhabited systems get trading hubs (50-80% range)
        int $minHubDistance = 100           // Minimum distance between hubs
    ) {
        $this->minGatesForHub = $minGatesForHub;
        $this->salvageYardProbability = $salvageYardProbability;
        $this->plansProbability = $plansProbability;
        $this->hubSpawnProbability = $hubSpawnProbability;
        $this->minHubDistance = $minHubDistance;
    }

    /**
     * Generate trading hubs for a galaxy based on gate intersections
     */
    public function generateHubs(Galaxy $galaxy): Collection
    {
        $hubs = collect();

        // Find POIs where multiple gates meet
        $hubLocations = $this->identifyHubLocations($galaxy);

        if ($hubLocations->isEmpty()) {
            return $hubs;
        }

        // Pre-load all POIs once for proximity calculations (memory optimization)
        // Only load coordinates, type, and attributes (for mineral production data)
        $allPois = $galaxy->pointsOfInterest()
            ->select(['id', 'galaxy_id', 'x', 'y', 'type', 'attributes'])
            ->get();

        // Pre-load minerals once
        $minerals = Mineral::all();

        // PRE-COMPUTE mineral sources ONCE (massive performance optimization)
        // Instead of filtering 15,750 POIs × 26 minerals × 1,000 hubs = 409M iterations
        // We filter 15,750 POIs × 26 minerals = 409,500 iterations (once)
        $mineralSources = $this->precomputeMineralSources($allPois, $minerals);

        // Create all hubs first (without stocking)
        foreach ($hubLocations as $location) {
            $hub = $this->createTradingHub($location);
            $hubs->push($hub);
        }

        // Batch stock all hubs with pre-computed data
        $this->batchStockHubs($hubs, $minerals, $allPois, $mineralSources);

        // Free memory
        unset($allPois, $minerals, $mineralSources);

        return $hubs;
    }

    /**
     * Pre-compute which POIs produce each mineral (runs once, not per-hub).
     *
     * @return array<string, Collection> Map of mineral symbol => POIs that produce it
     */
    private function precomputeMineralSources(Collection $allPois, Collection $minerals): array
    {
        $sources = [];

        foreach ($minerals as $mineral) {
            $sources[$mineral->symbol] = MineralSourceMapper::getPoisProducingMineral($allPois, $mineral->symbol);
        }

        return $sources;
    }

    /**
     * Stock all hubs using pre-computed mineral sources and batch inserts.
     */
    private function batchStockHubs(
        Collection $hubs,
        Collection $minerals,
        Collection $allPois,
        array $mineralSources
    ): void {
        if ($minerals->isEmpty() || $hubs->isEmpty()) {
            return;
        }

        $inventoryRows = [];
        $inventoriesToUpdate = [];

        foreach ($hubs as $hub) {
            $hubPoi = $hub->pointOfInterest;

            foreach ($minerals as $mineral) {
                // Use pre-computed sources instead of filtering all POIs each time
                $producingPois = $mineralSources[$mineral->symbol] ?? collect();

                // Calculate proximity data using pre-computed sources
                $proximityData = $this->calculateProximityWithSources($hubPoi, $mineral, $producingPois);
                $nearestDistance = $proximityData['nearest_distance'];

                // Decide if this hub stocks this mineral
                if (! ProximityPricingService::shouldStockMineral($hub, $mineral, $nearestDistance)) {
                    continue;
                }

                // Determine initial quantity
                $quantity = $this->determineInitialQuantity($hub, $mineral, $nearestDistance);

                $inventoryRows[] = [
                    'trading_hub_id' => $hub->id,
                    'mineral_id' => $mineral->id,
                    'quantity' => $quantity,
                    'current_price' => 0,
                    'buy_price' => 0,
                    'sell_price' => 0,
                    'demand_level' => $proximityData['demand_level'],
                    'supply_level' => $proximityData['supply_level'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Batch insert all inventory rows (1 query instead of thousands)
        if (! empty($inventoryRows)) {
            foreach (array_chunk($inventoryRows, 1000) as $chunk) {
                \Illuminate\Support\Facades\DB::table('trading_hub_inventories')->insert($chunk);
            }

            // Update pricing for all inventories (this triggers the pricing calculation)
            // Use chunked updates to avoid memory issues
            TradingHubInventory::whereIn('trading_hub_id', $hubs->pluck('id'))
                ->chunk(500, function ($inventories) {
                    foreach ($inventories as $inventory) {
                        $inventory->updatePricing();
                    }
                });
        }
    }

    /**
     * Calculate proximity data using pre-computed mineral sources.
     * This avoids re-filtering all POIs for each hub/mineral combination.
     */
    private function calculateProximityWithSources(
        PointOfInterest $hubPoi,
        Mineral $mineral,
        Collection $producingPois
    ): array {
        if ($producingPois->isEmpty()) {
            return [
                'demand_level' => 90,
                'supply_level' => 10,
                'nearest_distance' => null,
            ];
        }

        // Find nearest source
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($producingPois as $poi) {
            $dx = $poi->x - $hubPoi->x;
            $dy = $poi->y - $hubPoi->y;
            $distance = sqrt($dx * $dx + $dy * $dy);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
            }
        }

        // Calculate supply based on distance
        $supply = $this->calculateSupplyFromDistance($nearestDistance);

        // Calculate demand based on rarity and hub type
        $demand = $this->calculateDemandFromRarity($mineral);

        return [
            'demand_level' => $demand,
            'supply_level' => $supply,
            'nearest_distance' => $nearestDistance,
        ];
    }

    /**
     * Calculate supply level from distance to nearest source.
     */
    private function calculateSupplyFromDistance(float $distance): int
    {
        if ($distance < 1.0) {
            return (int) (100 - ($distance * 20));
        } elseif ($distance < 3.0) {
            return (int) (80 - (($distance - 1) * 10));
        } elseif ($distance < 6.0) {
            return (int) (60 - (($distance - 3) * 6.67));
        } elseif ($distance < 10.0) {
            return (int) (40 - (($distance - 6) * 5));
        } else {
            return max(5, (int) (20 - (($distance - 10) * 1)));
        }
    }

    /**
     * Calculate demand based on mineral rarity.
     */
    private function calculateDemandFromRarity(Mineral $mineral): int
    {
        $baseDemand = match ($mineral->rarity->value) {
            'abundant' => 30,
            'common' => 40,
            'uncommon' => 50,
            'rare' => 60,
            'very_rare' => 70,
            'epic' => 80,
            'legendary' => 90,
            'mythic' => 95,
        };

        // Add randomness
        return max(10, min(100, $baseDemand + mt_rand(-5, 5)));
    }

    /**
     * Identify POIs that qualify as trading hub locations
     * (inhabited star systems with warp gate connectivity)
     *
     * Uses probability and spacing to keep universe mostly empty for colonization
     */
    private function identifyHubLocations(Galaxy $galaxy): Collection
    {
        // Only consider INHABITED star systems
        $pois = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->where('is_inhabited', true)  // ONLY inhabited systems get trading hubs
            ->with(['outgoingGates', 'incomingGates'])
            ->get();

        // Filter by gate count and apply probability
        $candidates = $pois->filter(function ($poi) {
            $uniqueGateCount = $poi->outgoingGates->count();

            // Must have minimum gates for connectivity
            if ($uniqueGateCount < $this->minGatesForHub) {
                return false;
            }

            // Probability check - spawn hubs at X% of inhabited systems
            // Use straight probability (no scaling by gate count)
            return (mt_rand() / mt_getrandmax()) < $this->hubSpawnProbability;
        })->map(function ($poi) {
            $uniqueGateCount = $poi->outgoingGates->count();

            return [
                'poi' => $poi,
                'gate_count' => $uniqueGateCount,
            ];
        });

        // Apply spacing filter - remove hubs too close to each other
        return $this->applyMinimumSpacing($candidates);
    }

    /**
     * Filter hub locations to maintain minimum spacing
     * Keeps universe mostly empty for colonization
     */
    private function applyMinimumSpacing(Collection $candidates): Collection
    {
        $selected = collect();

        foreach ($candidates->sortByDesc('gate_count') as $candidate) { // Prioritize high-traffic hubs
            $poi = $candidate['poi'];
            $tooClose = false;

            foreach ($selected as $existing) {
                $existingPoi = $existing['poi'];
                $distance = sqrt(
                    pow($poi->x - $existingPoi->x, 2) +
                    pow($poi->y - $existingPoi->y, 2)
                );

                if ($distance < $this->minHubDistance) {
                    $tooClose = true;
                    break;
                }
            }

            if (! $tooClose) {
                $selected->push($candidate);
            }
        }

        return $selected;
    }

    /**
     * Create a trading hub at the specified location
     */
    private function createTradingHub(array $location): TradingHub
    {
        $poi = $location['poi'];
        $gateCount = $location['gate_count'];

        // Determine hub type based on gate count
        $type = $this->determineHubType($gateCount);

        // Determine if this hub should have a salvage yard
        $hasSalvageYard = $gateCount >= 4 && (random_int(1, 100) / 100) <= $this->salvageYardProbability;

        // Generate hub name based on POI name
        $hubName = $this->generateHubName($poi);

        // Determine tax rate (higher traffic = lower tax rate)
        $taxRate = max(2.0, 10.0 - ($gateCount * 0.5));

        // Determine services offered
        $services = $this->determineServices($type, $hasSalvageYard);

        $hub = TradingHub::create([
            'poi_id' => $poi->id,
            'name' => $hubName,
            'type' => $type,
            'has_salvage_yard' => $hasSalvageYard,
            'gate_count' => $gateCount,
            'tax_rate' => $taxRate,
            'services' => $services,
            'is_active' => true,
        ]);

        // Randomly assign plans to hub (5% chance by default)
        if ((random_int(1, 100) / 100) <= $this->plansProbability) {
            $hub->has_plans = true;
            $hub->save();
            $this->assignPlansToHub($hub);
        }

        return $hub;
    }

    /**
     * Determine hub type based on gate count
     */
    private function determineHubType(int $gateCount): string
    {
        if ($gateCount >= 5) {
            return 'premium';
        } elseif ($gateCount >= 3) {
            return 'major';
        }

        return 'standard';
    }

    /**
     * Generate a name for the trading hub
     */
    private function generateHubName(PointOfInterest $poi): string
    {
        $suffixes = [
            'Trading Post',
            'Commerce Hub',
            'Trading Station',
            'Mercantile Center',
            'Trade Exchange',
            'Commercial Nexus',
        ];

        return $poi->name.' '.$suffixes[array_rand($suffixes)];
    }

    /**
     * Determine what services this hub offers
     */
    private function determineServices(string $type, bool $hasSalvageYard): array
    {
        $services = ['mineral_trading'];

        if ($hasSalvageYard) {
            $services[] = 'ship_salvage';
            $services[] = 'ship_upgrades';
        }

        if ($type === 'major' || $type === 'premium') {
            $services[] = 'refueling';
            $services[] = 'repairs';
        }

        if ($type === 'premium') {
            $services[] = 'ship_sales';
            $services[] = 'advanced_upgrades';
            $services[] = 'storage';
        }

        return $services;
    }

    /**
     * Assign a random subset of plans to a trading hub
     */
    private function assignPlansToHub(TradingHub $hub): void
    {
        $allPlans = Plan::all();

        if ($allPlans->isEmpty()) {
            return;
        }

        $selectedPlans = collect();

        // Determine plan count by hub tier
        $planCount = match ($hub->type) {
            'premium' => random_int(10, 15),
            'major' => random_int(6, 10),
            'standard' => random_int(3, 6),
        };

        // Weight selection by rarity (favor common plans)
        $weights = [
            'rare' => 60,
            'epic' => 30,
            'legendary' => 10,
        ];

        for ($i = 0; $i < $planCount && $allPlans->isNotEmpty(); $i++) {
            $rarity = $this->weightedRandom($weights);
            $available = $allPlans->where('rarity', $rarity)
                ->whereNotIn('id', $selectedPlans->pluck('id'));

            if ($available->isEmpty()) {
                $available = $allPlans->whereNotIn('id', $selectedPlans->pluck('id'));
            }

            if ($available->isNotEmpty()) {
                $selectedPlans->push($available->random());
            }
        }

        // Attach to hub
        $hub->plans()->attach($selectedPlans->pluck('id'));
    }

    /**
     * Perform weighted random selection
     */
    private function weightedRandom(array $weights): string
    {
        $rand = random_int(1, array_sum($weights));
        $sum = 0;

        foreach ($weights as $key => $weight) {
            $sum += $weight;
            if ($rand <= $sum) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Determine if a hub should stock a particular mineral
     * (Deprecated - now using ProximityPricingService::shouldStockMineral)
     */
    private function shouldStockMineral(TradingHub $hub, Mineral $mineral): bool
    {
        // This method is kept for backwards compatibility
        // but is no longer used when proximity data is available
        return ProximityPricingService::shouldStockMineral($hub, $mineral, null);
    }

    /**
     * Determine initial quantity for a mineral based on proximity
     */
    private function determineInitialQuantity(TradingHub $hub, Mineral $mineral, ?float $nearestDistance): int
    {
        $baseQuantity = match ($hub->type) {
            'premium' => 10000,
            'major' => 5000,
            'standard' => 2000,
        };

        $rarityMultiplier = match ($mineral->rarity->value) {
            'abundant' => 3.0,
            'common' => 2.0,
            'uncommon' => 1.0,
            'rare' => 0.5,
            'very_rare' => 0.2,
            'epic' => 0.1,
            'legendary' => 0.05,
            'mythic' => 0.01,
        };

        // Apply proximity multiplier
        $proximityMultiplier = ProximityPricingService::calculateStockingMultiplier($nearestDistance);

        return (int) ($baseQuantity * $rarityMultiplier * $proximityMultiplier);
    }

    /**
     * Determine initial supply and demand levels
     * (Deprecated - now using ProximityPricingService::calculateProximityBasedLevels)
     */
    private function determineInitialSupplyDemand(Mineral $mineral): array
    {
        // This method is kept for backwards compatibility
        // but is no longer used when proximity data is available

        // Start with some randomness around the midpoint (50)
        $demand = random_int(30, 70);
        $supply = random_int(30, 70);

        // Rarer items tend to have higher demand, lower supply
        $rarityAdjustment = match ($mineral->rarity->value) {
            'abundant' => ['demand' => -10, 'supply' => 20],
            'common' => ['demand' => 0, 'supply' => 10],
            'uncommon' => ['demand' => 5, 'supply' => 0],
            'rare' => ['demand' => 10, 'supply' => -10],
            'very_rare' => ['demand' => 15, 'supply' => -15],
            'epic' => ['demand' => 20, 'supply' => -20],
            'legendary' => ['demand' => 25, 'supply' => -25],
            'mythic' => ['demand' => 30, 'supply' => -30],
        };

        $demand = max(0, min(100, $demand + $rarityAdjustment['demand']));
        $supply = max(0, min(100, $supply + $rarityAdjustment['supply']));

        return [$demand, $supply];
    }
}
