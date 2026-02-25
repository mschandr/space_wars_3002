<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingHub extends Model
{
    use HasFactory, HasUuid;

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
        'precursor_rumor_x',
        'precursor_rumor_y',
        'precursor_rumor_confidence',
        'precursor_bribe_cost',
        'shipyard_owner_name',
        'precursor_rumor_flavor',
    ];

    protected $casts = [
        'has_salvage_yard' => 'boolean',
        'has_plans' => 'boolean',
        'gate_count' => 'integer',
        'tax_rate' => 'decimal:2',
        'services' => 'array',
        'attributes' => 'array',
        'is_active' => 'boolean',
        'precursor_rumor_x' => 'integer',
        'precursor_rumor_y' => 'integer',
        'precursor_rumor_confidence' => 'float',
        'precursor_bribe_cost' => 'integer',
    ];

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

    /**
     * Check if this hub has a rumor about the Precursor ship
     */
    public function hasPrecursorRumor(): bool
    {
        return $this->precursor_rumor_x !== null && $this->precursor_rumor_y !== null;
    }

    /**
     * Check if a player has already obtained this hub's rumor
     */
    public function playerHasRumor(Player $player): bool
    {
        return PlayerPrecursorRumor::where('player_id', $player->id)
            ->where('trading_hub_id', $this->id)
            ->exists();
    }

    /**
     * Get all precursor rumors obtained from this hub
     */
    public function precursorRumors(): HasMany
    {
        return $this->hasMany(PlayerPrecursorRumor::class);
    }
}
