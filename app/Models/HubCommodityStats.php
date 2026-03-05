<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubCommodityStats extends Model
{
    protected $table = 'hub_commodity_stats';

    protected $fillable = [
        'trading_hub_id',
        'commodity_id',
        'avg_daily_demand',
        'avg_daily_supply',
        'cached_buy_price',
        'cached_sell_price',
        'last_computed_at',
    ];

    protected $casts = [
        'avg_daily_demand' => 'decimal:4',
        'avg_daily_supply' => 'decimal:4',
        'cached_buy_price' => 'decimal:2',
        'cached_sell_price' => 'decimal:2',
        'last_computed_at' => 'datetime',
    ];

    /**
     * Get the trading hub
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Get the commodity
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * Calculate coverage days (inventory duration)
     */
    public function getCoverageDays(): float
    {
        $inventory = $this->tradingHub
            ->inventories()
            ->where('mineral_id', $this->commodity_id)
            ->first();

        if (!$inventory) {
            return 0;
        }

        $onHand = (float)$inventory->on_hand_qty;
        $dailyDemand = (float)$this->avg_daily_demand ?: 1; // Epsilon to prevent division by zero

        return $onHand / $dailyDemand;
    }

    /**
     * Check if this stat needs recomputation
     */
    public function needsRecompute(int $intervalMinutes = 60): bool
    {
        if ($this->last_computed_at === null) {
            return true;
        }

        return $this->last_computed_at->diffInMinutes(now()) >= $intervalMinutes;
    }
}
