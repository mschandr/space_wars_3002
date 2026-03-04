<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstructionJob extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'trading_hub_id',
        'player_id',
        'blueprint_id',
        'quantity',
        'status',
        'inputs_consumed',
        'output_item_code',
        'started_at',
        'completes_at',
        'completed_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'inputs_consumed' => 'array',
        'started_at' => 'datetime',
        'completes_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the galaxy this job belongs to
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the trading hub where the build is happening
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Get the player who initiated the build
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the blueprint being built
     */
    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Scope: Pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope: Completed jobs
     */
    public function scopeComplete($query)
    {
        return $query->where('status', 'COMPLETE');
    }

    /**
     * Scope: Jobs by galaxy
     */
    public function scopeByGalaxy($query, Galaxy $galaxy)
    {
        return $query->where('galaxy_id', $galaxy->id);
    }

    /**
     * Check if job is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /**
     * Check if job is complete
     */
    public function isComplete(): bool
    {
        return $this->status === 'COMPLETE';
    }

    /**
     * Check if job has matured (completes_at <= now())
     */
    public function isMatured(): bool
    {
        return $this->completes_at <= now();
    }
}
