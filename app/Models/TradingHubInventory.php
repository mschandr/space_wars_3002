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
        'on_hand_qty',
        'reserved_qty',
        'current_price',
        'buy_price',
        'sell_price',
        'demand_level',
        'supply_level',
        'last_price_update',
        'last_snapshot_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'on_hand_qty' => 'decimal:4',
        'reserved_qty' => 'decimal:4',
        'current_price' => 'decimal:2',
        'buy_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'demand_level' => 'integer',
        'supply_level' => 'integer',
        'last_price_update' => 'datetime',
        'last_snapshot_at' => 'datetime',
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
     *
     * Uses on_hand_qty (ledger-backed inventory) which is the source of truth
     */
    public function hasStock(int $amount): bool
    {
        return (float)$this->on_hand_qty >= $amount;
    }
}
