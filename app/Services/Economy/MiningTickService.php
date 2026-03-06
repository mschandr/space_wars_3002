<?php

namespace App\Services\Economy;

use App\Models\Galaxy;
use App\Models\ResourceDeposit;
use App\Models\TradingHub;
use Illuminate\Support\Collection;

/**
 * MiningTickService
 *
 * Processes mining extraction for all active deposits in a tick.
 * - Each deposit extracts its max_extraction_per_tick amount (capped by max_total_qty)
 * - Routes to either assigned trading_hub_id or galaxy's first active hub
 * - Records ledger entries and applies to inventory
 * - Marks deposits as DEPLETED when max_total_qty is reached
 */
class MiningTickService
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly InventoryService $inventoryService,
    ) {}

    /**
     * Process mining extraction for all active deposits
     *
     * @param Galaxy|null $galaxy Filter to single galaxy, or null for all
     * @param bool $dryRun If true, does not write to database
     * @return array Results: ['processed' => int, 'total_extracted' => float, 'newly_depleted' => int, 'errors' => []]
     */
    public function processTick(?Galaxy $galaxy = null, bool $dryRun = false): array
    {
        $query = ResourceDeposit::active();

        if ($galaxy) {
            $query->where('galaxy_id', $galaxy->id);
        }

        $deposits = $query->with('commodity', 'galaxy', 'tradingHub')
            ->get();

        $results = [
            'processed' => 0,
            'total_extracted' => 0.0,
            'newly_depleted' => 0,
            'errors' => [],
        ];

        foreach ($deposits as $deposit) {
            try {
                $extracted = $this->processDeposit($deposit, $dryRun);

                if ($extracted > 0) {
                    $results['processed']++;
                    $results['total_extracted'] += $extracted;

                    if ($deposit->isDepleted()) {
                        $results['newly_depleted']++;
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'deposit_id' => $deposit->id,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Process a single deposit
     *
     * @return float Amount extracted
     */
    private function processDeposit(ResourceDeposit $deposit, bool $dryRun = false): float
    {
        // Get extraction amount (respects max_total_qty limit)
        $qty = $deposit->getExtractionThisTick();

        if ($qty <= 0) {
            return 0;
        }

        // Resolve which hub this extracts to
        $hub = $this->resolveHub($deposit);
        if (!$hub) {
            throw new \Exception("No trading hub found for deposit {$deposit->id}");
        }

        if (!$dryRun) {
            // Create ledger entry
            $entry = $this->ledgerService->recordMiningOutput(
                galaxy: $deposit->galaxy,
                hub: $hub,
                commodity: $deposit->commodity,
                qty: $qty
            );

            // Apply to inventory
            $this->inventoryService->applyLedgerEntry($entry);

            // Record extraction on deposit
            $deposit->recordExtraction($qty);
        }

        return $qty;
    }

    /**
     * Resolve which hub this deposit should extract to
     *
     * Returns the assigned hub, or falls back to galaxy's first active hub.
     *
     * @return TradingHub|null
     */
    private function resolveHub(ResourceDeposit $deposit): ?TradingHub
    {
        // If deposit has an assigned hub, use it
        if ($deposit->trading_hub_id) {
            return $deposit->tradingHub;
        }

        // Fall back to galaxy's first active hub
        return $deposit->galaxy
            ->tradingHubs()
            ->where('is_active', true)
            ->first();
    }
}
