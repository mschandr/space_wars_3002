<?php

namespace App\Services\Economy;

use App\Models\Commodity;
use App\Models\CommodityLedgerEntry;
use App\Models\TradingHub;
use App\Models\TradingHubInventory;
use Illuminate\Database\Eloquent\Collection;

/**
 * InventoryService
 *
 * Applies ledger entries to inventory.
 * This is the ONLY place hub inventory is modified.
 *
 * Key invariants:
 * - Inventory changes only happen via ledger entries
 * - For conserved commodities, inventory cannot go negative
 * - Every change is transactional and locked
 */
class InventoryService
{
    /**
     * Apply a ledger entry to inventory
     *
     * Uses row locks to prevent concurrent modification.
     */
    public function applyLedgerEntry(
        CommodityLedgerEntry $entry
    ): void {
        \DB::transaction(function () use ($entry) {
            // Lock the inventory row for this hub+commodity
            $inventory = TradingHubInventory::where('trading_hub_id', $entry->trading_hub_id)
                ->where('mineral_id', $entry->commodity_id)
                ->lockForUpdate()
                ->firstOrCreate(
                    [
                        'trading_hub_id' => $entry->trading_hub_id,
                        'mineral_id' => $entry->commodity_id,
                    ],
                    [
                        'on_hand_qty' => 0,
                        'reserved_qty' => 0,
                    ]
                );

            // Validate conservation for conserved commodities
            if ($entry->commodity->is_conserved) {
                $newQty = (float)$inventory->on_hand_qty + (float)$entry->qty_delta;

                if ($newQty < 0) {
                    throw new \Exception(
                        "Cannot reduce {$entry->commodity->code} below zero. "
                        . "Current: {$inventory->on_hand_qty}, Delta: {$entry->qty_delta}"
                    );
                }
            }

            // Apply the delta
            $inventory->on_hand_qty += $entry->qty_delta;
            $inventory->last_snapshot_at = now();
            $inventory->save();
        });
    }

    /**
     * Apply multiple ledger entries in one transaction
     *
     * Much more efficient than individual transactions for batch operations.
     */
    public function applyLedgerBatch(array $entries): void {
        \DB::transaction(function () use ($entries) {
            foreach ($entries as $entry) {
                // Lock and apply each entry
                $this->applyLedgerEntryLocked($entry);
            }
        });
    }

    /**
     * Get current on-hand quantity (fast path, no lock)
     */
    public function getOnHand(
        TradingHub $hub,
        Commodity $commodity
    ): float {
        $inventory = TradingHubInventory::where('trading_hub_id', $hub->id)
            ->where('mineral_id', $commodity->id)
            ->first();

        return $inventory ? (float)$inventory->on_hand_qty : 0;
    }

    /**
     * Get current reserved quantity
     */
    public function getReserved(
        TradingHub $hub,
        Commodity $commodity
    ): float {
        $inventory = TradingHubInventory::where('trading_hub_id', $hub->id)
            ->where('mineral_id', $commodity->id)
            ->first();

        return $inventory ? (float)$inventory->reserved_qty : 0;
    }

    /**
     * Get available quantity (on-hand minus reserved)
     */
    public function getAvailable(
        TradingHub $hub,
        Commodity $commodity
    ): float {
        $onHand = $this->getOnHand($hub, $commodity);
        $reserved = $this->getReserved($hub, $commodity);
        return max(0, $onHand - $reserved);
    }

    /**
     * Check if a ledger entry can be applied
     *
     * Used before applying to validate feasibility.
     */
    public function canApply(CommodityLedgerEntry $entry): bool {
        if (!$entry->commodity->is_conserved) {
            return true; // Soft commodities can always go negative
        }

        $currentQty = $this->getOnHand(
            $entry->tradingHub,
            $entry->commodity
        );

        $newQty = $currentQty + (float)$entry->qty_delta;

        return $newQty >= 0;
    }

    /**
     * Reconcile: Verify ledger total matches inventory
     *
     * Returns detailed audit info.
     */
    public function reconcile(
        TradingHub $hub,
        Commodity $commodity,
        ?\DateTimeInterface $since = null
    ): array {
        $ledgerService = app(LedgerService::class);

        // Sum all ledger deltas
        $ledgerTotal = $ledgerService->getLedgerTotal($hub, $commodity, $since);

        // Get actual on-hand
        $actualOnHand = $this->getOnHand($hub, $commodity);

        // Variance
        $variance = $actualOnHand - $ledgerTotal;

        return [
            'ledger_total' => $ledgerTotal,
            'on_hand' => $actualOnHand,
            'variance' => $variance,
            'is_balanced' => abs($variance) < 0.0001, // Float comparison tolerance
            'variance_percentage' => $actualOnHand > 0 ? ($variance / $actualOnHand) * 100 : 0,
        ];
    }

    /**
     * Reserve inventory (for pending orders, construction, etc.)
     */
    public function reserve(
        TradingHub $hub,
        Commodity $commodity,
        float $qty
    ): bool {
        $available = $this->getAvailable($hub, $commodity);

        if ($available < $qty) {
            return false;
        }

        \DB::transaction(function () use ($hub, $commodity, $qty) {
            $inventory = TradingHubInventory::where('trading_hub_id', $hub->id)
                ->where('mineral_id', $commodity->id)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $inventory->reserved_qty = (float)$inventory->reserved_qty + $qty;
                $inventory->save();
            }
        });

        return true;
    }

    /**
     * Release a reservation (if order is cancelled)
     */
    public function releaseReservation(
        TradingHub $hub,
        Commodity $commodity,
        float $qty
    ): void {
        \DB::transaction(function () use ($hub, $commodity, $qty) {
            $inventory = TradingHubInventory::where('trading_hub_id', $hub->id)
                ->where('mineral_id', $commodity->id)
                ->lockForUpdate()
                ->first();

            if ($inventory) {
                $inventory->reserved_qty = max(0, (float)$inventory->reserved_qty - $qty);
                $inventory->save();
            }
        });
    }

    /**
     * Get all inventories for a hub
     */
    public function getHubInventories(TradingHub $hub): Collection {
        return TradingHubInventory::where('trading_hub_id', $hub->id)
            ->with('mineral')
            ->get();
    }

    /**
     * Get total value of inventory at a hub (sum of qty * base_price)
     */
    public function getHubValue(TradingHub $hub): float {
        $inventories = $this->getHubInventories($hub);
        $value = 0;

        foreach ($inventories as $inv) {
            $value += (float)$inv->on_hand_qty * (float)$inv->mineral->base_price;
        }

        return $value;
    }

    /**
     * Internal: Apply entry with lock already held
     *
     * Used in batch operations where lock is held for entire transaction.
     */
    private function applyLedgerEntryLocked(CommodityLedgerEntry $entry): void {
        $inventory = TradingHubInventory::where('trading_hub_id', $entry->trading_hub_id)
            ->where('mineral_id', $entry->commodity_id)
            ->lockForUpdate()
            ->firstOrCreate(
                [
                    'trading_hub_id' => $entry->trading_hub_id,
                    'mineral_id' => $entry->commodity_id,
                ],
                [
                    'on_hand_qty' => 0,
                    'reserved_qty' => 0,
                ]
            );

        // Validate conservation
        if ($entry->commodity->is_conserved) {
            $newQty = (float)$inventory->on_hand_qty + (float)$entry->qty_delta;

            if ($newQty < 0) {
                throw new \Exception(
                    "Cannot reduce {$entry->commodity->code} below zero. "
                    . "Current: {$inventory->on_hand_qty}, Delta: {$entry->qty_delta}"
                );
            }
        }

        // Apply delta
        $inventory->on_hand_qty += $entry->qty_delta;
        $inventory->last_snapshot_at = now();
        $inventory->save();
    }
}
