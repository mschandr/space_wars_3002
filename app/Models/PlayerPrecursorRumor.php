<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks rumors about the Precursor ship that players have purchased from ship yards.
 *
 * Each ship yard has a (wrong) location they believe the Precursor ship is hidden.
 * Players can bribe the ship yard owner to get this information.
 * Collecting multiple rumors can help narrow down the true location... maybe.
 */
class PlayerPrecursorRumor extends Model
{
    protected $fillable = [
        'player_id',
        'trading_hub_id',
        'rumor_x',
        'rumor_y',
        'bribe_paid',
    ];

    protected $casts = [
        'rumor_x' => 'integer',
        'rumor_y' => 'integer',
        'bribe_paid' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }
}
