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
     * Check if there's enough stock to sell
     */
    public function hasStock(int $amount): bool
    {
        return $this->quantity >= $amount;
    }
}
