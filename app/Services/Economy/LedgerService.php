<?php

namespace App\Services\Economy;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ReasonCode;
use App\Models\Blueprint;
use App\Models\Commodity;
use App\Models\CommodityLedgerEntry;
use App\Models\Galaxy;
use App\Models\TradingHub;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * LedgerService
 *
 * Single source of truth for all commodity ledger entries.
 * This is the ONLY way to create ledger entries.
 * All inventory mutations must go through ledger first.
 *
 * Key invariant: No direct inventory mutation without a ledger entry.
 */
class LedgerService
{
    /**
     * Record mining output from a deposit
     */
    public function recordMiningOutput(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qty,
        ?int $actorId = null,
        ActorType $actorType = ActorType::SYSTEM,
        ?string $correlationId = null,
        array $metadata = []
    ): CommodityLedgerEntry {
        return $this->createEntry(
            galaxy: $galaxy,
            hub: $hub,
            commodity: $commodity,
            qtyDelta: $qty,
            reasonCode: ReasonCode::MINING,
            actorType: $actorType,
            actorId: $actorId,
            correlationId: $correlationId,
            metadata: $metadata
        );
    }

    /**
     * Record construction consumption (consumes inputs from blueprint)
     *
     * @return CommodityLedgerEntry[]
     */
    public function recordConstruction(
        Galaxy $galaxy,
        TradingHub $hub,
        Blueprint $blueprint,
        int $actorId,
        ?string $correlationId = null,
        array $metadata = []
    ): array {
        $correlationId ??= Str::uuid()->toString();
        $entries = [];

        // Create one ledger entry per input commodity
        foreach ($blueprint->getInputsWithCommodities() as $input) {
            $entries[] = $this->createEntry(
                galaxy: $galaxy,
                hub: $hub,
                commodity: $input->commodity,
                qtyDelta: -(float)$input->qty_required, // Negative = sink
                reasonCode: ReasonCode::CONSTRUCTION,
                actorType: ActorType::PLAYER,
                actorId: $actorId,
                correlationId: $correlationId,
                metadata: array_merge($metadata, ['blueprint_id' => $blueprint->id])
            );
        }

        return $entries;
    }

    /**
     * Record a trade (buy or sell)
     */
    public function recordTrade(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qty,
        float $pricePerUnit,
        string $tradeType, // 'buy' or 'sell'
        int $playerId,
        ?string $correlationId = null,
        array $metadata = []
    ): CommodityLedgerEntry {
        $reasonCode = match ($tradeType) {
            'buy' => ReasonCode::TRADE_BUY,
            'sell' => ReasonCode::TRADE_SELL,
            default => throw new \InvalidArgumentException("Invalid trade type: {$tradeType}")
        };

        // Buy: positive (inventory increases), Sell: negative (inventory decreases)
        $qtyDelta = $tradeType === 'buy' ? $qty : -$qty;

        return $this->createEntry(
            galaxy: $galaxy,
            hub: $hub,
            commodity: $commodity,
            qtyDelta: $qtyDelta,
            reasonCode: $reasonCode,
            actorType: ActorType::PLAYER,
            actorId: $playerId,
            correlationId: $correlationId,
            metadata: array_merge($metadata, ['price_per_unit' => $pricePerUnit])
        );
    }

    /**
     * Record upkeep/maintenance consumption
     */
    public function recordUpkeep(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qty,
        int $actorId,
        ?string $correlationId = null,
        array $metadata = []
    ): CommodityLedgerEntry {
        return $this->createEntry(
            galaxy: $galaxy,
            hub: $hub,
            commodity: $commodity,
            qtyDelta: -$qty, // Negative = sink
            reasonCode: ReasonCode::UPKEEP,
            actorType: ActorType::SYSTEM,
            actorId: $actorId,
            correlationId: $correlationId,
            metadata: $metadata
        );
    }

    /**
     * Record salvage recovery
     */
    public function recordSalvage(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qty,
        int $actorId,
        ?string $correlationId = null,
        array $metadata = []
    ): CommodityLedgerEntry {
        return $this->createEntry(
            galaxy: $galaxy,
            hub: $hub,
            commodity: $commodity,
            qtyDelta: $qty,
            reasonCode: ReasonCode::SALVAGE,
            actorType: ActorType::PLAYER,
            actorId: $actorId,
            correlationId: $correlationId,
            metadata: $metadata
        );
    }

    /**
     * Record NPC injection (controlled supply addition)
     */
    public function recordNpcInject(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qty,
        int $npcId,
        ?string $correlationId = null,
        array $metadata = []
    ): CommodityLedgerEntry {
        return $this->createEntry(
            galaxy: $galaxy,
            hub: $hub,
            commodity: $commodity,
            qtyDelta: $qty,
            reasonCode: ReasonCode::NPC_INJECT,
            actorType: ActorType::NPC,
            actorId: $npcId,
            correlationId: $correlationId,
            metadata: $metadata
        );
    }

    /**
     * Record genesis entry (backfill for existing inventory)
     *
     * Used when migrating from old system to ledger-based.
     * Creates one GENESIS entry per existing inventory to match on_hand_qty.
     */
    public function recordGenesis(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qty,
        ?string $correlationId = null,
        array $metadata = []
    ): CommodityLedgerEntry {
        return $this->createEntry(
            galaxy: $galaxy,
            hub: $hub,
            commodity: $commodity,
            qtyDelta: $qty,
            reasonCode: ReasonCode::GENESIS,
            actorType: ActorType::SYSTEM,
            actorId: null,
            correlationId: $correlationId,
            metadata: array_merge($metadata, ['migrated_from_old_quantity' => true])
        );
    }

    /**
     * Create multiple entries in a batch (for performance)
     *
     * @param array<array> $entries List of entry definitions
     * @return CommodityLedgerEntry[]
     */
    public function recordBatch(array $entries): array {
        $created = [];
        foreach ($entries as $entry) {
            $created[] = $this->createEntry(
                galaxy: $entry['galaxy'],
                hub: $entry['hub'],
                commodity: $entry['commodity'],
                qtyDelta: $entry['qty_delta'],
                reasonCode: $entry['reason_code'],
                actorType: $entry['actor_type'] ?? ActorType::SYSTEM,
                actorId: $entry['actor_id'] ?? null,
                correlationId: $entry['correlation_id'] ?? null,
                metadata: $entry['metadata'] ?? []
            );
        }
        return $created;
    }

    /**
     * Query ledger history
     */
    public function getLedgerHistory(
        Galaxy $galaxy,
        ?TradingHub $hub = null,
        ?Commodity $commodity = null,
        ?\DateTimeInterface $since = null,
        ?int $limit = null
    ): Collection {
        $query = CommodityLedgerEntry::where('galaxy_id', $galaxy->id);

        if ($hub) {
            $query->where('trading_hub_id', $hub->id);
        }

        if ($commodity) {
            $query->where('commodity_id', $commodity->id);
        }

        if ($since) {
            $query->where('timestamp', '>=', $since);
        }

        $query->orderBy('timestamp', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get ledger total for a commodity at a hub (sum of all deltas)
     */
    public function getLedgerTotal(
        TradingHub $hub,
        Commodity $commodity,
        ?\DateTimeInterface $since = null
    ): float {
        $query = CommodityLedgerEntry::where('trading_hub_id', $hub->id)
            ->where('commodity_id', $commodity->id);

        if ($since) {
            $query->where('timestamp', '>=', $since);
        }

        return (float)$query->sum('qty_delta');
    }

    /**
     * Get net sources and sinks separately
     */
    public function getLedgerBreakdown(
        TradingHub $hub,
        Commodity $commodity,
        ?\DateTimeInterface $since = null
    ): array {
        $query = CommodityLedgerEntry::where('trading_hub_id', $hub->id)
            ->where('commodity_id', $commodity->id);

        if ($since) {
            $query->where('timestamp', '>=', $since);
        }

        $entries = $query->get();

        $sources = 0;
        $sinks = 0;

        foreach ($entries as $entry) {
            if ($entry->qty_delta > 0) {
                $sources += $entry->qty_delta;
            } else {
                $sinks += abs($entry->qty_delta);
            }
        }

        return [
            'total' => $sources - $sinks,
            'sources' => $sources,
            'sinks' => $sinks,
            'entry_count' => $entries->count(),
        ];
    }

    /**
     * Core method: create a ledger entry
     *
     * This is the ONLY place ledger entries are created.
     */
    private function createEntry(
        Galaxy $galaxy,
        TradingHub $hub,
        Commodity $commodity,
        float $qtyDelta,
        ReasonCode $reasonCode,
        ActorType $actorType,
        ?int $actorId,
        ?string $correlationId,
        array $metadata
    ): CommodityLedgerEntry {
        $entry = CommodityLedgerEntry::create([
            'uuid' => Str::uuid()->toString(),
            'timestamp' => now(),
            'galaxy_id' => $galaxy->id,
            'trading_hub_id' => $hub->id,
            'commodity_id' => $commodity->id,
            'qty_delta' => $qtyDelta,
            'reason_code' => $reasonCode,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'correlation_id' => $correlationId,
            'metadata' => $metadata,
        ]);

        return $entry;
    }
}
