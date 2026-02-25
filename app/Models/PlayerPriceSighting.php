<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerPriceSighting extends Model
{
    protected $fillable = [
        'player_id',
        'trading_hub_id',
        'mineral_id',
        'buy_price',
        'sell_price',
        'quantity',
        'recorded_at',
    ];

    protected $casts = [
        'buy_price' => 'decimal:2',
        'sell_price' => 'decimal:2',
        'quantity' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    public function mineral(): BelongsTo
    {
        return $this->belongsTo(Mineral::class);
    }
}
