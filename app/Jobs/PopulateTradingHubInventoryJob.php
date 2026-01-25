<?php

namespace App\Jobs;

use App\Models\Galaxy;
use App\Models\Mineral;
use App\Models\TradingHub;
use App\Services\Trading\MineralSourceMapper;
use App\Services\Trading\ProximityPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously populates trading hub inventory after galaxy creation.
 * This job handles the most computationally expensive part of galaxy creation.
 */
class PopulateTradingHubInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes

    /**
     * Initialize the job for populating trading hub inventories for a specific galaxy.
     *
     * @param int $galaxyId ID of the galaxy whose trading hub inventories will be populated.
     * @param bool $regenerate If true, existing inventories will be rebuilt; otherwise existing inventories are preserved.
     */
    public function __construct(
        public int $galaxyId,
        public bool $regenerate = false
    ) {}

    /**
     * Populate trading hub inventories for the configured galaxy.
     *
     * Loads the galaxy by ID (exits silently if not found), preloads minerals and points of interest, builds per-mineral source data, processes each trading hub to create inventory rows and update pricing, and updates the galaxy's inventory_populated_at timestamp.
     */
    public function handle(): void
    {
        $galaxy = Galaxy::find($this->galaxyId);

        if (!$galaxy) {
            Log::warning("PopulateTradingHubInventoryJob: Galaxy {$this->galaxyId} not found");
            return;
        }

        Log::info("PopulateTradingHubInventoryJob: Starting inventory population for galaxy {$galaxy->name}");

        $startTime = microtime(true);

        // Pre-load data to reduce queries
        $minerals = Mineral::all();
        $allPois = $galaxy->pointsOfInterest()->get();

        // Pre-calculate mineral sources once (major optimization)
        $mineralSources = $this->buildMineralSourceIndex($allPois);

        // Get trading hubs
        $tradingHubs = TradingHub::whereHas('pointOfInterest', function ($query) use ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        })->with('pointOfInterest')->get();

        Log::info("PopulateTradingHubInventoryJob: Processing {$tradingHubs->count()} hubs with {$minerals->count()} minerals");

        $hubsProcessed = 0;
        $inventoryCreated = 0;

        foreach ($tradingHubs as $hub) {
            $hubInventory = $this->processHubInventory($hub, $minerals, $allPois, $mineralSources);
            $inventoryCreated += $hubInventory;
            $hubsProcessed++;

            // Log progress every 10 hubs
            if ($hubsProcessed % 10 === 0) {
                Log::info("PopulateTradingHubInventoryJob: Processed {$hubsProcessed}/{$tradingHubs->count()} hubs");
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        Log::info("PopulateTradingHubInventoryJob: Completed in {$duration}s - {$inventoryCreated} inventory items created");

        // Update galaxy metadata
        $galaxy->setAttribute('inventory_populated_at', now());
        $galaxy->save();
    }

    /**
         * Build an index mapping each mineral symbol to a list of producing POI coordinates.
         *
         * @param iterable|array $allPois Collection or array of POI objects; each POI is expected to expose `x`, `y` and an `attributes['produces']` array of mineral symbols.
         * @return array<string, array<int, array{ x: float|int, y: float|int }>> Array where keys are mineral symbols and values are arrays of coordinate arrays with keys `x` and `y`.
         */
    private function buildMineralSourceIndex($allPois): array
    {
        $index = [];

        foreach ($allPois as $poi) {
            $produces = $poi->attributes['produces'] ?? [];
            foreach ($produces as $mineralSymbol) {
                if (!isset($index[$mineralSymbol])) {
                    $index[$mineralSymbol] = [];
                }
                $index[$mineralSymbol][] = [
                    'x' => $poi->x,
                    'y' => $poi->y,
                ];
            }
        }

        return $index;
    }

    /**
         * Populate inventory rows for a trading hub based on mineral proximity and stocking rules.
         *
         * Builds proximity data per mineral, decides whether the hub should stock each mineral,
         * determines quantity when stocked, performs a bulk insert of inventory rows, and
         * triggers pricing updates for the hub's inventories.
         *
         * @param TradingHub $hub The trading hub being populated.
         * @param iterable|Collection $minerals Iterable collection of Mineral models to consider.
         * @param Collection|array $allPois All points of interest for the galaxy (preloaded).
         * @param array $mineralSources Map of mineral symbol => list of source coordinates (['x'=>float,'y'=>float]).
         * @return int The number of inventory items created for the hub.
         */
    private function processHubInventory(
        TradingHub $hub,
        $minerals,
        $allPois,
        array $mineralSources
    ): int {
        $hubPoi = $hub->pointOfInterest;
        $inventoryCount = 0;
        $inventoryData = [];

        foreach ($minerals as $mineral) {
            // Use pre-built index for O(1) lookup instead of filtering allPois
            $sources = $mineralSources[$mineral->symbol] ?? [];

            if (empty($sources)) {
                // No sources - high demand, low supply
                $proximityData = [
                    'demand_level' => 90,
                    'supply_level' => 10,
                    'nearest_distance' => null,
                ];
            } else {
                // Find nearest source using pre-indexed coordinates
                $nearestDistance = PHP_FLOAT_MAX;
                foreach ($sources as $source) {
                    $distance = sqrt(
                        pow($hubPoi->x - $source['x'], 2) +
                        pow($hubPoi->y - $source['y'], 2)
                    );
                    if ($distance < $nearestDistance) {
                        $nearestDistance = $distance;
                    }
                }

                $proximityData = [
                    'demand_level' => $this->calculateDemand($mineral, $hub),
                    'supply_level' => $this->calculateSupply($nearestDistance),
                    'nearest_distance' => $nearestDistance,
                ];
            }

            $nearestDistance = $proximityData['nearest_distance'];

            // Check if hub should stock this mineral
            if (!ProximityPricingService::shouldStockMineral($hub, $mineral, $nearestDistance)) {
                continue;
            }

            $quantity = $this->determineQuantity($hub, $mineral, $nearestDistance);

            $inventoryData[] = [
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
            $inventoryCount++;
        }

        // Bulk insert for performance
        if (!empty($inventoryData)) {
            DB::table('trading_hub_inventories')->insert($inventoryData);

            // Update pricing for all inserted items
            $hub->inventories()->each(fn($inv) => $inv->updatePricing());
        }

        return $inventoryCount;
    }

    /**
     * Map a distance to a relative supply level using distance bands.
     *
     * Computes an integer supply level that decreases as distance from a mineral source increases,
     * using piecewise bands with progressively shallower declines and a minimum floor.
     *
     * @param float $distance Distance to the nearest mineral source (map units).
     * @return int Supply level as an integer between 5 and 100, where higher values indicate greater supply.
     */
    private function calculateSupply(float $distance): int
    {
        if ($distance < 1.0) return (int)(100 - ($distance * 20));
        if ($distance < 3.0) return (int)(80 - (($distance - 1) * 10));
        if ($distance < 6.0) return (int)(60 - (($distance - 3) * 6.67));
        if ($distance < 10.0) return (int)(40 - (($distance - 6) * 5));
        return max(5, (int)(20 - (($distance - 10) * 1)));
    }

    /**
     * Compute the demand level for a mineral at a given trading hub.
     *
     * Computes a demand score based on the mineral's rarity, applies a connectivity
     * bonus determined by the hub type, adds a small random adjustment, and clamps
     * the result to the range 10–100.
     *
     * @param Mineral $mineral Mineral whose rarity is used to determine base demand.
     * @param TradingHub $hub Trading hub whose type can modify demand via a connectivity bonus.
     * @return int Demand level between 10 and 100 inclusive.
     */
    private function calculateDemand(Mineral $mineral, TradingHub $hub): int
    {
        $baseDemand = match ($mineral->rarity->value) {
            'abundant' => 30, 'common' => 40, 'uncommon' => 50,
            'rare' => 60, 'very_rare' => 70, 'epic' => 80,
            'legendary' => 90, 'mythic' => 95, default => 50,
        };

        $connectivityBonus = match ($hub->type) {
            'premium' => 10, 'major' => 5, default => 0,
        };

        return max(10, min(100, $baseDemand + $connectivityBonus + mt_rand(-5, 5)));
    }

    /**
     * Calculate the number of units of a mineral to stock at a trading hub.
     *
     * The result is derived from a hub-type base quantity, a multiplier based on the mineral's rarity,
     * and a proximity stocking multiplier computed from the nearest source distance.
     *
     * @param TradingHub $hub The trading hub whose type influences the base stock level.
     * @param Mineral $mineral The mineral whose rarity influences the rarity multiplier.
     * @param float|null $nearestDistance Distance to the nearest producing POI, or `null` if no source is known.
     * @return int The final integer quantity to stock for the given hub and mineral.
     */
    private function determineQuantity(TradingHub $hub, Mineral $mineral, ?float $nearestDistance): int
    {
        $baseQuantity = match ($hub->type) {
            'premium' => 10000, 'major' => 5000, default => 2000,
        };

        $rarityMultiplier = match ($mineral->rarity->value) {
            'abundant' => 3.0, 'common' => 2.0, 'uncommon' => 1.0,
            'rare' => 0.5, 'very_rare' => 0.2, 'epic' => 0.1,
            'legendary' => 0.05, 'mythic' => 0.01, default => 1.0,
        };

        $proximityMultiplier = ProximityPricingService::calculateStockingMultiplier($nearestDistance);

        return (int)($baseQuantity * $rarityMultiplier * $proximityMultiplier);
    }

    /**
         * Log error details when the job fails.
         *
         * Logs the exception message and stack trace along with the job's galaxy ID for debugging.
         *
         * @param \Throwable $exception The exception thrown during job execution.
         */
    public function failed(\Throwable $exception): void
    {
        Log::error("PopulateTradingHubInventoryJob: Failed for galaxy {$this->galaxyId}", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}