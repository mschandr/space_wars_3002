<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    protected $fillable = [
        'uuid',
        'name',
        'component',
        'description',
        'additional_levels',
        'price',
        'rarity',
        'requirements',
    ];

    protected $casts = [
        'additional_levels' => 'integer',
        'price' => 'decimal:2',
        'requirements' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($plan) {
            if (empty($plan->uuid)) {
                $plan->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get all players who own this plan
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'player_plans')
                    ->withTimestamps()
                    ->withPivot('acquired_at');
    }

    /**
     * Get all trading hubs that sell this plan
     */
    public function tradingHubs(): BelongsToMany
    {
        return $this->belongsToMany(TradingHub::class, 'trading_hub_plans')
                    ->withTimestamps();
    }

    /**
     * Get human-readable component name
     */
    public function getComponentDisplayName(): string
    {
        return ucwords(str_replace('_', ' ', $this->component));
    }

    /**
     * Get tier name based on additional levels
     */
    public function getTierName(): string
    {
        return match($this->additional_levels) {
            10 => 'Basic',
            20 => 'Advanced',
            30 => 'Experimental',
            default => 'Unknown',
        };
    }

    /**
     * Get full display name (tier + component)
     */
    public function getFullName(): string
    {
        return "{$this->getTierName()} {$this->getComponentDisplayName()} Plans";
    }
}
