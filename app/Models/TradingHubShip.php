<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingHubShip extends Model
{
    protected $fillable = [
        'trading_hub_id',
        'ship_id',
        'quantity',
        'current_price',
        'demand_level',
        'supply_level',
        'last_price_update',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'current_price' => 'decimal:2',
        'demand_level' => 'integer',
        'supply_level' => 'integer',
        'last_price_update' => 'datetime',
    ];

    /**
     * Get the trading hub that owns this ship inventory
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Get the ship type
     */
    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    /**
     * Update pricing based on supply and demand
     */
    public function updatePricing(): void
    {
        $basePrice = $this->ship->base_price;

        // Calculate multipliers based on supply and demand levels
        $demandMultiplier = 1 + (($this->demand_level - 50) / 100);
        $supplyMultiplier = 1 - (($this->supply_level - 50) / 100);

        $this->current_price = $basePrice * $demandMultiplier * $supplyMultiplier;
        $this->last_price_update = now();
        $this->save();
    }

    /**
     * Check if ship is in stock
     */
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    /**
     * Decrease stock by one
     */
    public function decreaseStock(): bool
    {
        if ($this->quantity <= 0) {
            return false;
        }

        $this->quantity--;
        $this->save();

        return true;
    }
}
