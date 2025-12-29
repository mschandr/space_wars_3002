<?php

namespace App\Services\Trading;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Support\Collection;

class TradingHubGenerator
{
    private int $minGatesForHub;
    private float $salvageYardProbability;

    public function __construct(
        int $minGatesForHub = 2,
        float $salvageYardProbability = 0.3
    ) {
        $this->minGatesForHub = $minGatesForHub;
        $this->salvageYardProbability = $salvageYardProbability;
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
     * (locations where multiple warp gates intersect)
     */
    private function identifyHubLocations(Galaxy $galaxy): Collection
    {
        $pois = $galaxy->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->with(['outgoingGates', 'incomingGates'])
            ->get();

        return $pois->filter(function ($poi) {
            $totalGates = $poi->outgoingGates->count() + $poi->incomingGates->count();
            return $totalGates >= $this->minGatesForHub * 2; // *2 because gates are bidirectional
        })->map(function ($poi) {
            $uniqueGateCount = $poi->outgoingGates->count(); // Only count outgoing to avoid double-counting
            return [
                'poi' => $poi,
                'gate_count' => $uniqueGateCount,
            ];
        });
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

        return TradingHub::create([
            'poi_id' => $poi->id,
            'name' => $hubName,
            'type' => $type,
            'has_salvage_yard' => $hasSalvageYard,
            'gate_count' => $gateCount,
            'tax_rate' => $taxRate,
            'services' => $services,
            'is_active' => true,
        ]);
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
