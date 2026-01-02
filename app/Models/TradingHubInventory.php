<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingHubInventory extends Model
{
    use HasFactory;
    protected $fillable = [
        'trading_hub_id',
        'mineral_id',
        'quantity',
        'current_price',
        'buy_price',
        'sell_price',
        'demand_level',
        'supply_level',
        'last_price_update',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'current_price' => 'decimal:2',
        'buy_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'demand_level' => 'integer',
        'supply_level' => 'integer',
        'last_price_update' => 'datetime',
    ];

    /**
     * Get the trading hub this inventory belongs to
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Get the mineral for this inventory
     */
    public function mineral(): BelongsTo
    {
        return $this->belongsTo(Mineral::class);
    }

    /**
     * Update prices based on supply and demand
     */
    public function updatePricing(): void
    {
        $mineral = $this->mineral;
        $baseValue = $mineral->getMarketValue();

        // Supply/demand affects price (demand increases price, supply decreases it)
        $demandMultiplier = 1 + (($this->demand_level - 50) / 100);
        $supplyMultiplier = 1 - (($this->supply_level - 50) / 100);

        $this->current_price = $baseValue * $demandMultiplier * $supplyMultiplier;

        // Apply market event multipliers (Drug Wars style!)
        $eventService = app(\App\Services\MarketEventService::class);
        $eventMultiplier = $eventService->getCombinedMultiplier($this->mineral_id, $this->trading_hub_id);
        $this->current_price = $this->current_price * $eventMultiplier;

        // Hub buys at lower price, sells at higher price (spread)
        $spread = 0.15; // 15% spread
        $this->buy_price = $this->current_price * (1 - $spread);
        $this->sell_price = $this->current_price * (1 + $spread);

        $this->last_price_update = now();
        $this->save();
    }

    /**
     * Check if there's enough stock to sell
     */
    public function hasStock(int $amount): bool
    {
        return $this->quantity >= $amount;
    }

    /**
     * Add stock to inventory
     */
    public function addStock(int $amount): void
    {
        $this->quantity += $amount;
        $this->supply_level = min(100, $this->supply_level + ($amount / 10));
        $this->save();
        $this->updatePricing();
    }

    /**
     * Remove stock from inventory
     */
    public function removeStock(int $amount): bool
    {
        if (!$this->hasStock($amount)) {
            return false;
        }

        $this->quantity -= $amount;
        $this->demand_level = min(100, $this->demand_level + ($amount / 10));
        $this->save();
        $this->updatePricing();

        return true;
    }
}
