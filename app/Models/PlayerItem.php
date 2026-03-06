<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerItem extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'player_id',
        'trading_hub_id',
        'construction_job_id',
        'item_code',
        'quantity',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    public function constructionJob(): BelongsTo
    {
        return $this->belongsTo(ConstructionJob::class);
    }

    /**
     * Scope: items ready for pickup
     */
    public function scopeReadyForPickup($query)
    {
        return $query->where('status', 'ready_for_pickup');
    }

    /**
     * Scope: items by hub
     */
    public function scopeAtHub($query, TradingHub $hub)
    {
        return $query->where('trading_hub_id', $hub->id);
    }
}
