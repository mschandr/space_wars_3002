<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\StellarCartographer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Star Chart Service
 *
 * Handles star chart purchasing, coverage calculation, and system information revelation
 */
class StarChartService
{
    /**
     * Get chart coverage from a purchase location
     * Uses BFS to traverse warp gates up to maxHops distance
     * Optimized: Pre-loads gates per BFS level to reduce N+1 queries
     *
     * @param  PointOfInterest  $purchaseLocation  Center of the chart
     * @param  int  $maxHops  Number of warp gate hops to traverse
     * @return Collection Collection of POIs revealed by this chart
     */
    public function getChartCoverage(PointOfInterest $purchaseLocation, int $maxHops = 2): Collection
    {
        $maxHops = $maxHops ?: config('game_config.star_charts.coverage_hops', 2);

        $visited = collect([$purchaseLocation->id]);
        $revealed = collect([$purchaseLocation]);
        $queue = collect([$purchaseLocation]);

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $currentLevel = $queue;
            $queue = collect();

            if ($currentLevel->isEmpty()) {
                break;
            }

            // Batch load all gates for current level systems (prevents N+1)
            $currentLevelIds = $currentLevel->pluck('id')->toArray();
            $gates = DB::table('warp_gates')
                ->whereIn('source_poi_id', $currentLevelIds)
                ->where('is_hidden', false)
                ->where('status', 'active')
                ->get();

            // Get all destination POI IDs that we need
            $destinationIds = $gates->pluck('destination_poi_id')
                ->filter(fn ($id) => ! $visited->contains($id))
                ->unique()
                ->toArray();

            if (empty($destinationIds)) {
                continue;
            }

            // Batch load all destination POIs
            $destinations = PointOfInterest::whereIn('id', $destinationIds)
                ->where('is_hidden', false)
                ->get()
                ->keyBy('id');

            foreach ($destinations as $destination) {
                if ($visited->contains($destination->id)) {
                    continue;
                }

                $visited->push($destination->id);
                $revealed->push($destination);
                $queue->push($destination);
            }
        }

        return $revealed;
    }

    /**
     * Calculate chart price based on unknown systems
     * Uses exponential pricing: basePrice * (multiplier ^ unknownCount)
     * Optimized: Uses cached charted POI IDs to prevent N+1 queries
     *
     * @param  PointOfInterest  $purchaseLocation  Center of the chart
     * @param  Player  $player  The player purchasing
     * @param  StellarCartographer|null  $shop  The cartographer shop (for markup)
     * @return float Price in credits
     */
    public function calculateChartPrice(
        PointOfInterest $purchaseLocation,
        Player $player,
        ?StellarCartographer $shop = null
    ): float {
        $coverage = $this->getChartCoverage($purchaseLocation);

        // Get charted POI IDs once (cached, prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        // TODO: (Optimization) in_array() is O(n) per lookup. Use array_flip($chartedPoiIds) for
        // O(1) lookups with isset(), especially important as chart collections grow large.
        $unknownCount = 0;
        foreach ($coverage as $poi) {
            if (! in_array($poi->id, $chartedPoiIds, true)) {
                $unknownCount++;
            }
        }

        // If player already has all charts in coverage, return 0 (or minimum price)
        if ($unknownCount === 0) {
            return 0;
        }

        // Exponential pricing
        $basePrice = config('game_config.star_charts.base_price', 1000);
        $multiplier = config('game_config.star_charts.unknown_multiplier', 1.5);

        $price = $basePrice * pow($multiplier, $unknownCount - 1);

        // Apply shop markup if provided
        if ($shop) {
            $price *= $shop->markup_multiplier;
        }

        return round($price, 2);
    }

    /**
     * Purchase a chart and unlock systems for player
     * Optimized: Uses cached charted POI IDs and batch inserts
     *
     * @param  Player  $player  The player
     * @param  StellarCartographer  $shop  The cartographer shop
     * @param  PointOfInterest  $centerSystem  Center of chart coverage
     * @return array Result with success status and systems revealed
     */
    public function purchaseChart(
        Player $player,
        StellarCartographer $shop,
        PointOfInterest $centerSystem
    ): array {
        $coverage = $this->getChartCoverage($centerSystem);
        $price = $this->calculateChartPrice($centerSystem, $player, $shop);

        // Check if player already has all charts
        if ($price === 0.0) {
            return [
                'success' => false,
                'message' => 'You already have charts for all systems in this region',
                'systems_revealed' => 0,
            ];
        }

        // Check if player has enough credits
        if ($player->credits < $price) {
            return [
                'success' => false,
                'message' => 'Insufficient credits',
                'required' => $price,
                'available' => $player->credits,
                'systems_revealed' => 0,
            ];
        }

        // Deduct credits
        $player->deductCredits($price);

        // Get charted POI IDs once (prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        // Prepare batch insert for new charts
        $purchaseTime = now();
        $newCharts = [];

        foreach ($coverage as $poi) {
            if (! in_array($poi->id, $chartedPoiIds, true)) {
                $newCharts[] = [
                    'player_id' => $player->id,
                    'revealed_poi_id' => $poi->id,
                    'purchased_from_poi_id' => $shop->poi_id,
                    'price_paid' => $price,
                    'purchased_at' => $purchaseTime,
                    'created_at' => $purchaseTime,
                    'updated_at' => $purchaseTime,
                ];
            }
        }

        // Batch insert all new charts at once
        if (! empty($newCharts)) {
            DB::table('player_star_charts')->insert($newCharts);
        }

        // Clear the chart cache so future checks reflect new charts
        $player->clearChartedPoiCache();

        return [
            'success' => true,
            'message' => 'Star chart purchased successfully',
            'systems_revealed' => count($newCharts),
            'total_systems' => $coverage->count(),
            'price_paid' => $price,
            'credits_remaining' => $player->credits,
        ];
    }

    /**
     * Get available charts at a shop (excluding already purchased)
     * Optimized: Uses cached charted POI IDs to prevent N+1 queries
     *
     * @param  StellarCartographer  $shop  The cartographer shop
     * @param  Player  $player  The player
     * @return Collection Collection of available chart options
     */
    public function getAvailableCharts(StellarCartographer $shop, Player $player): Collection
    {
        $shopLocation = $shop->pointOfInterest;

        // For now, we'll offer charts for systems within coverage of the shop
        // In the future, could offer multiple "packages" at different hop distances
        $coverage = $this->getChartCoverage($shopLocation);

        // Get charted POI IDs once (prevents N+1 queries)
        $chartedPoiIds = $player->getChartedPoiIds();

        // Filter to only systems the player doesn't have charts for (in-memory)
        $unknownSystems = $coverage->filter(function ($poi) use ($chartedPoiIds) {
            return ! in_array($poi->id, $chartedPoiIds, true);
        });

        if ($unknownSystems->isEmpty()) {
            return collect();
        }

        // Create a chart option (price calculation will reuse the cached IDs)
        $price = $this->calculateChartPrice($shopLocation, $player, $shop);

        return collect([
            [
                'center_poi' => $shopLocation,
                'coverage' => $coverage,
                'unknown_systems' => $unknownSystems->count(),
                'total_systems' => $coverage->count(),
                'price' => $price,
                'name' => $this->generateChartName($shopLocation),
            ],
        ]);
    }

    /**
     * Get revealed information for a system
     * Optimized: Uses cached charted POI IDs
     *
     * @param  PointOfInterest  $poi  The system
     * @param  Player  $player  The player
     * @return array|null System information or null if no chart
     */
    public function getSystemInfo(PointOfInterest $poi, Player $player): ?array
    {
        // Use optimized in-memory lookup
        if (! $player->hasChartForId($poi->id)) {
            return null;
        }

        $pirateInfo = $this->detectPiratePresence($poi, $player);

        // Get connected systems (non-hidden warp gates)
        $connections = $poi->outgoingGates()
            ->where('is_hidden', false)
            ->with('destinationPoi')
            ->get()
            ->map(fn ($gate) => $gate->destinationPoi->name)
            ->toArray();

        return [
            'name' => $poi->name,
            'coordinates' => [$poi->x, $poi->y],
            'type' => $poi->type->label(),
            'is_inhabited' => $poi->is_inhabited,
            'has_trading_hub' => $poi->tradingHub && $poi->tradingHub->is_active,
            'pirate_warning' => $pirateInfo['has_pirates'] ? $pirateInfo['confidence'] : 'None',
            'connections' => $connections,
        ];
    }

    /**
     * Detect pirate presence with probabilistic accuracy
     *
     * @param  PointOfInterest  $poi  The system
     * @param  Player  $player  The player (for sensor bonus)
     * @return array Detection result
     */
    public function detectPiratePresence(PointOfInterest $poi, Player $player): array
    {
        // Check for actual pirates on warp lanes to/from this system
        $actualPirates = DB::table('warp_lane_pirates')
            ->join('warp_gates', 'warp_lane_pirates.warp_gate_id', '=', 'warp_gates.id')
            ->where(function ($query) use ($poi) {
                $query->where('warp_gates.source_poi_id', $poi->id)
                    ->orWhere('warp_gates.destination_poi_id', $poi->id);
            })
            ->exists();

        // Calculate detection accuracy
        $baseAccuracy = config('game_config.star_charts.pirate_detection_base_accuracy', 0.70);
        $sensorBonus = config('game_config.star_charts.pirate_detection_sensor_bonus', 0.05);
        $maxAccuracy = config('game_config.star_charts.pirate_detection_max_accuracy', 0.95);

        $sensorLevel = $player->activeShip?->sensors ?? 1;
        $accuracy = min($maxAccuracy, $baseAccuracy + ($sensorBonus * ($sensorLevel - 1)));

        // Probabilistic detection (can have false negatives, but not false positives)
        $detected = $actualPirates && (mt_rand(1, 100) / 100) <= $accuracy;

        // Determine confidence level based on sensor level
        $confidence = match (true) {
            $sensorLevel >= 5 => 'High',
            $sensorLevel >= 3 => 'Medium',
            default => 'Low',
        };

        return [
            'has_pirates' => $detected,
            'confidence' => $confidence,
            'accuracy' => round($accuracy * 100, 1),
        ];
    }

    /**
     * Grant free starting charts to a new player
     * Reveals 2-3 closest inhabited systems
     *
     * @param  Player  $player  The new player
     * @return int Number of charts granted
     */
    public function grantStartingCharts(Player $player): int
    {
        $startingLocation = $player->currentLocation;
        $chartCount = config('game_config.star_charts.starting_charts_count', 3);

        if (! $startingLocation) {
            return 0;
        }

        // Find closest inhabited systems
        $closestSystems = PointOfInterest::where('galaxy_id', $startingLocation->galaxy_id)
            ->stars()
            ->inhabited()
            ->where('is_hidden', false)
            ->where('id', '!=', $startingLocation->id)
            ->get()
            ->sortBy(function ($poi) use ($startingLocation) {
                return sqrt(
                    pow($poi->x - $startingLocation->x, 2) +
                    pow($poi->y - $startingLocation->y, 2)
                );
            })
            ->take($chartCount);

        $grantedCount = 0;
        $now = now();

        foreach ($closestSystems as $system) {
            DB::table('player_star_charts')->insert([
                'player_id' => $player->id,
                'revealed_poi_id' => $system->id,
                'purchased_from_poi_id' => $startingLocation->id,
                'price_paid' => 0.00,
                'purchased_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $grantedCount++;
        }

        return $grantedCount;
    }

    /**
     * Generate a descriptive name for a star chart
     *
     * @param  PointOfInterest  $centerPoi  Center of the chart
     * @return string Chart name
     */
    private function generateChartName(PointOfInterest $centerPoi): string
    {
        return "Star Chart: {$centerPoi->name} Region";
    }
}
