<?php

namespace App\Console\Commands;

use App\DataObjects\PricingContext;
use App\Models\Mineral;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use App\Services\Trading\HubInventoryMutationService;
use Illuminate\Console\Command;

class NpcTraderTickCommand extends Command
{
    protected $signature = 'npc:trade-tick {--hub-count=5 : Number of hubs to process}';
    protected $description = 'Simulate NPC trader activity: random hub pairs exchanging goods';

    public function __construct(
        private readonly HubInventoryMutationService $mutationService
    ) {
        parent::__construct();
    }

    /**
     * Execute the command
     */
    public function handle(): int
    {
        // Check if feature is enabled
        if (!config('features.npc_traders')) {
            $this->info('NPC trader feature is disabled in config/features.php');
            return 0;
        }

        if (!config('economy.npc_traders.enabled')) {
            $this->info('NPC traders disabled in config/economy.php');
            return 0;
        }

        $hubCount = (int) $this->option('hub-count');
        $processedCount = 0;

        // Get random hubs
        $hubs = TradingHub::query()
            ->where('is_active', true)
            ->with('inventories.mineral', 'pointOfInterest')
            ->inRandomOrder()
            ->limit($hubCount)
            ->get();

        foreach ($hubs as $hub) {
            // Find a random mineral to trade
            $inventory = $hub->inventories()
                ->where('quantity', '>', 0)
                ->inRandomOrder()
                ->first();

            if (!$inventory) {
                continue;
            }

            // Simulate trade activity
            $amount = random_int(5, 50);  // Trade 5-50 units
            $direction = random_int(0, 1) === 0 ? 'buy' : 'sell';

            // Don't exceed available quantity
            if ($direction === 'buy') {
                $amount = min($amount, $inventory->quantity);
            }

            // Apply the trade
            try {
                $ctx = PricingContext::forHub($hub);
                $this->mutationService->applyTrade($inventory, $amount, $direction, $ctx);

                $processedCount++;
                $this->line("NPC traded: {$amount} units of {$inventory->mineral->name} ({$direction}) at {$hub->name}");
            } catch (\Exception $e) {
                $this->error("Error trading at {$hub->name}: {$e->getMessage()}");
            }
        }

        $this->info("NPC trading cycle complete. {$processedCount} trades executed.");

        return 0;
    }
}
