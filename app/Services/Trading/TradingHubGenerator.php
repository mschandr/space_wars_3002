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

        foreach ($hubLocations as $location) {
            $hub = $this->createTradingHub($location);
            $hubs->push($hub);

            // Stock the hub with minerals
            $this->stockHub($hub);
        }

        return $hubs;
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

            if (!$tooClose) {
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

        return $poi->name . ' ' . $suffixes[array_rand($suffixes)];
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
     * Stock a trading hub with minerals
     */
    private function stockHub(TradingHub $hub): void
    {
        $minerals = Mineral::all();

        if ($minerals->isEmpty()) {
            return;
        }

        // Get all POIs in the galaxy for proximity calculations
        $allPois = $hub->pointOfInterest->galaxy->pointsOfInterest;

        foreach ($minerals as $mineral) {
            // Calculate proximity-based levels
            $proximityData = ProximityPricingService::calculateProximityBasedLevels(
                $hub,
                $mineral,
                $allPois
            );

            $nearestDistance = $proximityData['nearest_distance'];

            // Decide if this hub stocks this mineral based on proximity
            if (!ProximityPricingService::shouldStockMineral($hub, $mineral, $nearestDistance)) {
                continue;
            }

            // Determine initial quantity based on proximity
            $quantity = $this->determineInitialQuantity($hub, $mineral, $nearestDistance);

            // Create inventory entry
            $inventory = TradingHubInventory::create([
                'trading_hub_id' => $hub->id,
                'mineral_id' => $mineral->id,
                'quantity' => $quantity,
                'current_price' => 0,
                'buy_price' => 0,
                'sell_price' => 0,
                'demand_level' => $proximityData['demand_level'],
                'supply_level' => $proximityData['supply_level'],
            ]);

            // Update pricing based on supply/demand
            $inventory->updatePricing();
        }
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
        $planCount = match($hub->type) {
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
        $baseQuantity = match($hub->type) {
            'premium' => 10000,
            'major' => 5000,
            'standard' => 2000,
        };

        $rarityMultiplier = match($mineral->rarity->value) {
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
        $rarityAdjustment = match($mineral->rarity->value) {
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
