<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerTradeTransaction extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'player_id',
        'trading_hub_id',
        'mineral_id',
        'transaction_type',
        'quantity',
        'unit_price',
        'total_amount',
        'credits_after',
        'transacted_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'credits_after' => 'decimal:2',
        'transacted_at' => 'datetime',
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
