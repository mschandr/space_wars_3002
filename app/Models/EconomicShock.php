<?php

namespace App\Models;

use App\Enums\Economy\ActorType;
use App\Enums\Economy\ShockType;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EconomicShock extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'commodity_id',
        'shock_type',
        'magnitude',
        'decay_half_life_ticks',
        'starts_at',
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
     */
    public function getEffectiveMagnitude(int $atTickNumber): float
    {
        $startedAt = $this->starts_at;
        $elapsedTicks = $atTickNumber - $startedAt->timestamp; // Rough approximation

        $decayRate = log(2) / (float)$this->decay_half_life_ticks;
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
