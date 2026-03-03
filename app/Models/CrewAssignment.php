<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewAssignment extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'crew_member_id',
        'trading_hub_id',
    ];

    /**
     * Get the galaxy this assignment belongs to
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the crew member for this assignment
     */
    public function crewMember(): BelongsTo
    {
        return $this->belongsTo(CrewMember::class);
    }

    /**
     * Get the trading hub where this crew is stationed
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }
}
