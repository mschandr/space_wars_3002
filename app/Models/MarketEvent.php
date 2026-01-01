<?php

namespace App\Models;

use App\Enums\MarketEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MarketEvent extends Model
{
    protected $fillable = [
        'uuid',
        'mineral_id',
        'trading_hub_id',
        'event_type',
        'price_multiplier',
        'description',
        'started_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'event_type' => MarketEventType::class,
        'price_multiplier' => 'decimal:2',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($event) {
            if (empty($event->uuid)) {
                $event->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the mineral affected by this event (null = all minerals)
     */
    public function mineral(): BelongsTo
    {
        return $this->belongsTo(Mineral::class);
    }

    /**
     * Get the trading hub affected by this event (null = galaxy-wide)
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Check if this event is currently active
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active
            && $this->started_at <= now()
            && $this->expires_at > now();
    }

    /**
     * Check if this event has expired
     */
    public function hasExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Deactivate this event
     */
    public function deactivate(): void
    {
        $this->is_active = false;
        $this->save();
    }

    /**
     * Check if this event affects a specific mineral
     */
    public function affectsMineral(?int $mineralId): bool
    {
        // Global events (null mineral_id) affect all minerals
        if ($this->mineral_id === null) {
            return true;
        }

        return $this->mineral_id === $mineralId;
    }

    /**
     * Check if this event affects a specific trading hub
     */
    public function affectsTradingHub(?int $tradingHubId): bool
    {
        // Galaxy-wide events (null trading_hub_id) affect all hubs
        if ($this->trading_hub_id === null) {
            return true;
        }

        return $this->trading_hub_id === $tradingHubId;
    }

    /**
     * Get the formatted duration string
     */
    public function getDurationString(): string
    {
        $minutes = $this->started_at->diffInMinutes($this->expires_at);
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$mins}m";
        }

        return "{$mins}m";
    }

    /**
     * Get the time remaining string
     */
    public function getTimeRemainingString(): string
    {
        if (!$this->isCurrentlyActive()) {
            return 'Expired';
        }

        $minutes = now()->diffInMinutes($this->expires_at);
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$mins}m remaining";
        }

        return "{$mins}m remaining";
    }
}
