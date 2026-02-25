<?php

namespace App\Models;

use App\Enums\RarityTier;
use App\Enums\SlotType;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ship Component Blueprint
 *
 * Represents a type of component that can be installed on ships.
 * Components are categorized into 8 slot types:
 * - Core systems (1 per ship): engine, reactor, hull_plating, shield_generator, sensor_array, cargo_module
 * - Multi-slot systems: weapon, utility
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
        'max_upgrade_level',
        'upgrade_cost_base',
        'size_class',
    ];

    protected $casts = [
        'slots_required' => 'integer',
        'base_price' => 'decimal:2',
        'rarity' => RarityTier::class,
        'slot_type' => SlotType::class,
        'effects' => 'array',
        'requirements' => 'array',
        'is_available' => 'boolean',
        'max_upgrade_level' => 'integer',
        'upgrade_cost_base' => 'decimal:2',
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
     * Get the effect value for a stat at a given upgrade level.
     * Each level adds 15% of base value.
     */
    public function getUpgradedEffect(string $stat, int $level): mixed
    {
        $base = $this->getEffect($stat);

        if ($base === null || ! is_numeric($base)) {
            return $base;
        }

        $perLevel = config('game_config.components.upgrade_effect_per_level', 0.15);

        return $base * (1 + $level * $perLevel);
    }

    /**
     * Check if this component can fit on the given ship (size class check).
     */
    public function canFitShip(PlayerShip $ship): bool
    {
        if ($this->size_class === 'any') {
            return true;
        }

        return $this->size_class === $ship->size_class;
    }

    /**
     * Check if this is a weapon component
     */
    public function isWeapon(): bool
    {
        return $this->slot_type === SlotType::WEAPON;
    }

    /**
     * Check if this is a utility component
     */
    public function isUtility(): bool
    {
        return $this->slot_type === SlotType::UTILITY;
    }

    /**
     * Get rarity color for display
     */
    public function getRarityColor(): string
    {
        return $this->rarity->color();
    }
}
