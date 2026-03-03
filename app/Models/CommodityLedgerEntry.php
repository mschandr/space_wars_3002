<?php

namespace App\Models;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ReasonCode;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommodityLedgerEntry extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'timestamp',
        'galaxy_id',
        'trading_hub_id',
        'commodity_id',
        'qty_delta',
        'reason_code',
        'actor_type',
        'actor_id',
        'correlation_id',
        'metadata',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'qty_delta' => 'decimal:4',
        'reason_code' => ReasonCode::class,
        'actor_type' => ActorType::class,
        'metadata' => 'array',
    ];

    /**
     * Ledger entries are immutable — disable updates
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('Ledger entries are immutable');
    }

    /**
     * Get the commodity
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * Get the trading hub
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Get the galaxy
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Scope: entries since a date
     */
    public function scopeSince($query, \DateTimeInterface $date)
    {
        return $query->where('timestamp', '>=', $date);
    }

    /**
     * Scope: entries by reason
     */
    public function scopeByReason($query, ReasonCode $reason)
    {
        return $query->where('reason_code', $reason);
    }

    /**
     * Scope: entries by actor
     */
    public function scopeByActor($query, ActorType $type, ?int $actorId = null)
    {
        $query->where('actor_type', $type);
        if ($actorId !== null) {
            $query->where('actor_id', $actorId);
        }
        return $query;
    }

    /**
     * Scope: only sources (positive delta)
     */
    public function scopeSources($query)
    {
        return $query->where('qty_delta', '>', 0);
    }

    /**
     * Scope: only sinks (negative delta)
     */
    public function scopeSinks($query)
    {
        return $query->where('qty_delta', '<', 0);
    }
}
