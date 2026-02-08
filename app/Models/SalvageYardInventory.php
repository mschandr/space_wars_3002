<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salvage Yard Inventory - components available for sale at trading hubs.
 *
 * Salvage yards sell:
 * - Weapons (lasers, missiles, torpedoes) for weapon_slots
 * - Utilities (shield regenerators, hull patches, scanners) for utility_slots
 *
 * Items may be:
 * - Salvage: Recovered from wrecks (may be damaged)
 * - Manufactured: Factory new (full condition)
 * - Stolen: Pirate loot (discounted but risky?)
 */
class SalvageYardInventory extends Model
{
    use HasFactory;

    protected $table = 'salvage_yard_inventory';

    protected $fillable = [
        'trading_hub_id',
        'ship_component_id',
        'quantity',
        'current_price',
        'condition',
        'source',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'current_price' => 'decimal:2',
        'condition' => 'integer',
    ];

    /**
     * Get the trading hub selling this item
     */
    public function tradingHub(): BelongsTo
    {
        return $this->belongsTo(TradingHub::class);
    }

    /**
     * Get the component being sold
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(ShipComponent::class, 'ship_component_id');
    }

    /**
     * Check if this is salvage (potentially damaged)
     */
    public function isSalvage(): bool
    {
        return $this->source === 'salvage';
    }

    /**
     * Check if this is factory new
     */
    public function isNew(): bool
    {
        return $this->source === 'manufactured' && $this->condition === 100;
    }

    /**
     * Get the condition description
     */
    public function getConditionDescription(): string
    {
        return match (true) {
            $this->condition === 100 => 'Pristine',
            $this->condition >= 80 => 'Good',
            $this->condition >= 60 => 'Fair',
            $this->condition >= 40 => 'Poor',
            $this->condition >= 20 => 'Damaged',
            default => 'Broken',
        };
    }

    /**
     * Get the source description
     */
    public function getSourceDescription(): string
    {
        return match ($this->source) {
            'manufactured' => 'Factory New',
            'salvage' => 'Salvaged',
            'stolen' => 'Black Market',
            default => 'Unknown Origin',
        };
    }

    /**
     * Calculate price discount based on condition
     */
    public function getPriceDiscount(): float
    {
        if ($this->condition === 100) {
            return 0;
        }

        // Up to 50% discount for heavily damaged items
        return (100 - $this->condition) / 200;
    }
}
