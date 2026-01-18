<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TradingHub extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'poi_id',
        'name',
        'type',
        'has_salvage_yard',
        'has_plans',
        'gate_count',
        'tax_rate',
        'services',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'has_salvage_yard' => 'boolean',
        'has_plans' => 'boolean',
        'gate_count' => 'integer',
        'tax_rate' => 'decimal:2',
        'services' => 'array',
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($hub) {
            if (empty($hub->uuid)) {
                $hub->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the point of interest where this hub is located
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Get all inventory items for this trading hub
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(TradingHubInventory::class);
    }

    /**
     * Get all plans available at this trading hub
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'trading_hub_plans')
            ->withTimestamps();
    }

    /**
     * Get all ships available at this trading hub
     */
    public function ships(): HasMany
    {
        return $this->hasMany(TradingHubShip::class);
    }

    /**
     * Check if this hub sells ships
     */
    public function hasShipyard(): bool
    {
        return $this->ships()->where('quantity', '>', 0)->exists();
    }

    /**
     * Get the hub's tier based on gate count
     */
    public function getTier(): string
    {
        if ($this->gate_count >= 5) {
            return 'premium';
        } elseif ($this->gate_count >= 3) {
            return 'major';
        }

        return 'standard';
    }

    /**
     * Calculate tax amount for a transaction
     */
    public function calculateTax(float $amount): float
    {
        return $amount * ($this->tax_rate / 100);
    }

    /**
     * Check if this hub has a specific service
     */
    public function hasService(string $service): bool
    {
        return in_array($service, $this->services ?? []);
    }

    /**
     * Get icon for hub type
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'premium' => '⭐',
            'major' => '●',
            'standard' => '○',
        };
    }
}
