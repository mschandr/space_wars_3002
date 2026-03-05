<?php

namespace App\Models;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ShockType;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EconomicShock extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'commodity_id',
        'shock_type',
        'magnitude',
        'decay_half_life_ticks',
        'starts_at',
        'started_at_tick',
        'ends_at',
        'is_active',
        'triggered_by_actor_id',
        'triggered_by_actor_type',
        'metadata',
    ];

    protected $casts = [
        'shock_type' => ShockType::class,
        'magnitude' => 'decimal:3',
        'decay_half_life_ticks' => 'integer',
        'started_at_tick' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'triggered_by_actor_type' => ActorType::class,
        'metadata' => 'array',
    ];

    /**
     * Get the galaxy
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the commodity (nullable for system-wide shocks)
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * Scope: only active shocks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: by shock type
     */
    public function scopeOfType($query, ShockType $type)
    {
        return $query->where('shock_type', $type);
    }

    /**
     * Compute effective magnitude at a given tick number
     * Uses exponential decay: effective = magnitude * exp(-decay_rate * elapsed_ticks)
     *
     * @param int $atTickNumber The current game tick number
     * @return float Effective magnitude after decay
     *
     * Formula:
     * - decay_rate = ln(2) / decay_half_life_ticks
     * - elapsed_ticks = atTickNumber - started_at_tick
     * - effective = magnitude * exp(-decay_rate * elapsed_ticks)
     *
     * Example:
     * - magnitude = 1.0, half_life = 100 ticks
     * - At tick 100 (elapsed=100): effective = 1.0 * exp(-0.00693 * 100) = 0.5
     * - At tick 200 (elapsed=200): effective = 1.0 * exp(-0.00693 * 200) = 0.25
     */
    public function getEffectiveMagnitude(int $atTickNumber): float
    {
        // Compute elapsed ticks in consistent units
        $elapsedTicks = $atTickNumber - $this->started_at_tick;

        // Exponential decay rate: at half_life ticks, magnitude = 50%
        $decayRate = log(2) / (float)$this->decay_half_life_ticks;

        // Apply decay formula
        $effective = (float)$this->magnitude * exp(-$decayRate * $elapsedTicks);

        return $effective;
    }

    /**
     * Check if shock is fully decayed (< 1% remaining)
     */
    public function isFullyDecayed(int $atTickNumber): bool
    {
        return abs($this->getEffectiveMagnitude($atTickNumber)) < 0.01;
    }

    /**
     * Mark shock as inactive
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
            'ends_at' => now(),
        ]);
    }
}
