<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ship Component Blueprint
 *
 * Represents a type of component that can be installed on ships:
 * - Weapons (lasers, missiles, torpedoes) - fill weapon_slots
 * - Shields (regenerators, boosters) - fill utility_slots
 * - Hull (patches, reinforcement) - fill utility_slots
 * - Utilities (scanners, cargo expanders) - fill utility_slots
 */
class ShipComponent extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'slot_type',
        'description',
        'slots_required',
        'base_price',
        'rarity',
        'effects',
        'requirements',
        'is_available',
    ];

    protected $casts = [
        'slots_required' => 'integer',
        'base_price' => 'decimal:2',
        'effects' => 'array',
        'requirements' => 'array',
        'is_available' => 'boolean',
    ];

    /**
     * Get all instances of this component installed on ships
     */
    public function installations(): HasMany
    {
        return $this->hasMany(PlayerShipComponent::class);
    }

    /**
     * Get all salvage yard listings for this component
     */
    public function salvageListings(): HasMany
    {
        return $this->hasMany(SalvageYardInventory::class);
    }

    /**
     * Check if a player meets the requirements to use this component
     */
    public function meetsRequirements(Player $player): bool
    {
        if (empty($this->requirements)) {
            return true;
        }

        foreach ($this->requirements as $requirement => $value) {
            if ($requirement === 'level' && $player->level < $value) {
                return false;
            }

            // Check ship stats if player has active ship
            if ($player->activeShip) {
                $shipStat = $player->activeShip->{$requirement} ?? null;
                if ($shipStat !== null && $shipStat < $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get the effect value for a specific stat
     */
    public function getEffect(string $stat): mixed
    {
        return $this->effects[$stat] ?? null;
    }

    /**
     * Check if this is a weapon component
     */
    public function isWeapon(): bool
    {
        return $this->slot_type === 'weapon_slot';
    }

    /**
     * Check if this is a utility component
     */
    public function isUtility(): bool
    {
        return $this->slot_type === 'utility_slot';
    }

    /**
     * Get rarity color for display
     */
    public function getRarityColor(): string
    {
        return match ($this->rarity) {
            'exotic' => 'red',
            'unique' => 'orange',
            'epic' => 'purple',
            'rare' => 'blue',
            'uncommon' => 'green',
            default => 'gray',
        };
    }
}
