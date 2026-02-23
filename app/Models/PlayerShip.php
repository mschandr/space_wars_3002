<?php

namespace App\Models;

use App\Enums\SlotType;
use App\Models\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlayerShip extends Model
{
    use HasFactory, HasUuid;

    const FUEL_REGEN_RATE_DEFAULT = 30; // fallback if config missing

    /**
     * Get base fuel regen rate (seconds per fuel unit) from config.
     */
    public static function fuelRegenRate(): int
    {
        return (int) config('game_config.ships.fuel_regen_seconds_per_unit', self::FUEL_REGEN_RATE_DEFAULT);
    }

    protected $fillable = [
        'uuid',
        'player_id',
        'ship_id',
        'current_poi_id',
        'name',
        'current_fuel',
        'max_fuel',
        'fuel_last_updated_at',
        'hull',
        'max_hull',
        'weapons',
        'cargo_hold',
        'sensors',
        'warp_drive',
        'weapon_slots',
        'utility_slots',
        'engine_slots',
        'reactor_slots',
        'hull_plating_slots',
        'shield_slots',
        'sensor_slots',
        'cargo_module_slots',
        'size_class',
        'shield_strength',
        'fuel_regen_modifier',
        'fuel_consumption_modifier',
        'speed_modifier',
        'hidden_hold_capacity',
        'hidden_cargo',
        'colonist_capacity',
        'current_colonists',
        'current_cargo',
        'is_active',
        'status',
        'variation_traits',
    ];

    protected $casts = [
        'current_fuel' => 'integer',
        'max_fuel' => 'integer',
        'fuel_last_updated_at' => 'datetime',
        'hull' => 'integer',
        'max_hull' => 'integer',
        'weapons' => 'integer',
        'cargo_hold' => 'integer',
        'sensors' => 'integer',
        'warp_drive' => 'integer',
        'weapon_slots' => 'integer',
        'utility_slots' => 'integer',
        'engine_slots' => 'integer',
        'reactor_slots' => 'integer',
        'hull_plating_slots' => 'integer',
        'shield_slots' => 'integer',
        'sensor_slots' => 'integer',
        'cargo_module_slots' => 'integer',
        'shield_strength' => 'integer',
        'fuel_regen_modifier' => 'float',
        'fuel_consumption_modifier' => 'float',
        'speed_modifier' => 'float',
        'hidden_hold_capacity' => 'integer',
        'hidden_cargo' => 'integer',
        'colonist_capacity' => 'integer',
        'current_colonists' => 'integer',
        'current_cargo' => 'integer',
        'is_active' => 'boolean',
        'variation_traits' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($playerShip) {
            if ($playerShip->is_active) {
                $playerShip->regenerateFuel();
            }
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'current_poi_id');
    }

    public function cargo(): HasMany
    {
        return $this->hasMany(PlayerCargo::class);
    }

    /**
     * Get docked fighters on this carrier ship
     */
    public function fighters(): HasMany
    {
        return $this->hasMany(PlayerShipFighter::class);
    }

    /**
     * Get installed components on this ship
     */
    public function components(): HasMany
    {
        return $this->hasMany(PlayerShipComponent::class);
    }

    /**
     * Get installed components of a specific slot type.
     */
    public function componentsOfType(SlotType $type): HasMany
    {
        return $this->hasMany(PlayerShipComponent::class)->where('slot_type', $type->value);
    }

    /**
     * Get installed weapon components.
     *
     * Note: Named weaponComponents() to avoid shadowing the 'weapons' integer attribute.
     */
    public function weaponComponents(): HasMany
    {
        return $this->componentsOfType(SlotType::WEAPON);
    }

    /**
     * Get installed utility components.
     *
     * Note: Named utilityComponents() for consistency with weaponComponents().
     */
    public function utilityComponents(): HasMany
    {
        return $this->componentsOfType(SlotType::UTILITY);
    }

    /**
     * Get number of available slots for a given slot type.
     */
    public function getAvailableSlots(SlotType $type): int
    {
        $usedSlots = $this->components()
            ->where('slot_type', $type->value)
            ->with('component')
            ->get()
            ->sum(fn ($c) => $c->component->slots_required ?? 1);

        $totalSlots = (int) ($this->{$type->slotColumn()} ?? 0);

        return max(0, $totalSlots - $usedSlots);
    }

    /**
     * Get number of available weapon slots
     */
    public function getAvailableWeaponSlots(): int
    {
        return $this->getAvailableSlots(SlotType::WEAPON);
    }

    /**
     * Get number of available utility slots
     */
    public function getAvailableUtilitySlots(): int
    {
        return $this->getAvailableSlots(SlotType::UTILITY);
    }

    // =========================================================================
    // EFFECTIVE STATS (base column + component effects)
    // =========================================================================

    /**
     * Sum a specific effect key across all installed components.
     */
    public function getComponentEffectTotal(string $effectKey): float
    {
        if (! $this->relationLoaded('components')) {
            $this->load('components.component');
        }

        return $this->components->sum(function ($playerComponent) use ($effectKey) {
            $effects = $playerComponent->component->effects ?? [];

            return (float) ($effects[$effectKey] ?? 0);
        });
    }

    /**
     * Effective cargo hold: base cargo_hold + cargo_boost from components.
     */
    public function getEffectiveCargoHold(): int
    {
        return $this->cargo_hold + (int) $this->getComponentEffectTotal('cargo_boost');
    }

    /**
     * Effective max hull: base max_hull + hull_boost from components.
     */
    public function getEffectiveMaxHull(): int
    {
        return $this->max_hull + (int) $this->getComponentEffectTotal('hull_boost');
    }

    /**
     * Effective shield strength: base shield_strength + shield_boost from components.
     */
    public function getEffectiveShieldStrength(): int
    {
        return ($this->shield_strength ?? 0) + (int) $this->getComponentEffectTotal('shield_boost');
    }

    /**
     * Effective max fuel: base max_fuel + fuel_capacity from components.
     */
    public function getEffectiveMaxFuel(): int
    {
        return $this->max_fuel + (int) $this->getComponentEffectTotal('fuel_capacity');
    }

    /**
     * Effective sensor level: base sensors + sensor_boost from components.
     */
    public function getEffectiveSensors(): int
    {
        return $this->sensors + (int) $this->getComponentEffectTotal('sensor_boost');
    }

    /**
     * Available cargo space using effective stats.
     */
    public function getAvailableCargoSpace(): int
    {
        return $this->getEffectiveCargoHold() - $this->current_cargo;
    }

    /**
     * Check if this ship is a carrier
     */
    public function isCarrier(): bool
    {
        return $this->ship->attributes['is_carrier'] ?? false;
    }

    /**
     * Get fighter capacity for this carrier
     */
    public function getFighterCapacity(): int
    {
        if (! $this->isCarrier()) {
            return 0;
        }

        return $this->ship->attributes['fighter_capacity'] ?? 0;
    }

    /**
     * Check if carrier has room for more fighters
     */
    public function canAddFighter(): bool
    {
        if (! $this->isCarrier()) {
            return false;
        }

        return $this->fighters()->count() < $this->getFighterCapacity();
    }

    /**
     * Calculate and update fuel based on time elapsed.
     * Applies fuel_regen_modifier for ship variations (e.g., 1.2 = 20% faster, 0.8 = 20% slower)
     *
     * Auto-triggered via the Eloquent 'retrieved' event in boot(), so fuel is always
     * up-to-date whenever a PlayerShip is loaded from the database.
     */
    public function regenerateFuel(): void
    {
        $effectiveMaxFuel = $this->getEffectiveMaxFuel();

        if ($this->current_fuel >= $effectiveMaxFuel) {
            return;
        }

        $now = Carbon::now();
        $lastUpdate = Carbon::parse($this->fuel_last_updated_at);
        $secondsElapsed = (int) abs($now->diffInSeconds($lastUpdate));

        // Apply fuel regen modifier and warp drive bonus (higher = faster regen = lower seconds per fuel)
        $regenModifier = $this->fuel_regen_modifier ?? 1.0;
        $componentRegenBonus = $this->getComponentEffectTotal('fuel_regen');
        $warpDriveBonus = 1 + ($this->warp_drive - 1) * 0.3;
        $effectiveRegenRate = max(1, (int) round(self::fuelRegenRate() / ($regenModifier * $warpDriveBonus * (1 + $componentRegenBonus))));

        $fuelToRegenerate = (int) floor($secondsElapsed / $effectiveRegenRate);

        if ($fuelToRegenerate > 0) {
            $this->current_fuel = min($effectiveMaxFuel, $this->current_fuel + $fuelToRegenerate);
            $this->fuel_last_updated_at = $now->subSeconds($secondsElapsed % $effectiveRegenRate);
            $this->save();
        }
    }

    /**
     * Get current fuel after regeneration
     */
    public function getCurrentFuel(): int
    {
        $this->regenerateFuel();

        return $this->current_fuel;
    }

    /**
     * Consume fuel for travel.
     * Applies fuel_consumption_modifier for ship variations (e.g., 1.4 = 40% more fuel usage)
     */
    public function consumeFuel(int $amount): bool
    {
        $this->regenerateFuel();

        // Apply fuel consumption modifier (higher modifier = more fuel used)
        $consumptionModifier = $this->fuel_consumption_modifier ?? 1.0;
        $modifiedAmount = (int) ceil($amount * $consumptionModifier);

        $effectiveConsumption = max(1, (int) floor($modifiedAmount / $this->warp_drive));

        if ($this->current_fuel < $effectiveConsumption) {
            return false;
        }

        $this->current_fuel -= $effectiveConsumption;
        $this->fuel_last_updated_at = Carbon::now();
        $this->save();

        return true;
    }

    /**
     * Get effective fuel consumption for a given base amount.
     * Useful for displaying estimated fuel costs to players.
     */
    public function getEffectiveFuelConsumption(int $baseAmount): int
    {
        $consumptionModifier = $this->fuel_consumption_modifier ?? 1.0;
        $modifiedAmount = (int) ceil($baseAmount * $consumptionModifier);

        return max(1, (int) floor($modifiedAmount / $this->warp_drive));
    }

    /**
     * Get time until fuel is full
     */
    public function getTimeUntilFullFuel(): int
    {
        $this->regenerateFuel();

        $effectiveMaxFuel = $this->getEffectiveMaxFuel();

        if ($this->current_fuel >= $effectiveMaxFuel) {
            return 0;
        }

        $fuelNeeded = $effectiveMaxFuel - $this->current_fuel;
        $regenModifier = $this->fuel_regen_modifier ?? 1.0;
        $componentRegenBonus = $this->getComponentEffectTotal('fuel_regen');
        $warpDriveBonus = 1 + ($this->warp_drive - 1) * 0.3;
        $effectiveRegenRate = max(1, (int) round(self::fuelRegenRate() / ($regenModifier * $warpDriveBonus * (1 + $componentRegenBonus))));

        return $fuelNeeded * $effectiveRegenRate;
    }

    /**
     * Take damage
     */
    public function takeDamage(int $damage): void
    {
        $this->hull = max(0, $this->hull - $damage);
        $effectiveMaxHull = $this->getEffectiveMaxHull();

        if ($this->hull <= 0) {
            $this->status = 'destroyed';
        } elseif ($this->hull < $effectiveMaxHull * 0.3) {
            $this->status = 'damaged';
        }

        $this->save();
    }

    /**
     * Repair hull
     */
    public function repair(int $amount): void
    {
        $effectiveMaxHull = $this->getEffectiveMaxHull();
        $this->hull = min($effectiveMaxHull, $this->hull + $amount);

        if ($this->hull > $effectiveMaxHull * 0.3) {
            $this->status = 'operational';
        }

        $this->save();
    }

    /**
     * Check if ship can carry more cargo
     */
    public function canAddCargo(int $amount): bool
    {
        return ($this->current_cargo + $amount) <= $this->getEffectiveCargoHold();
    }

    /**
     * Add cargo
     */
    public function addCargo(int $amount): bool
    {
        if (! $this->canAddCargo($amount)) {
            return false;
        }

        $this->current_cargo += $amount;
        $this->save();

        return true;
    }

    /**
     * Remove cargo
     */
    public function removeCargo(int $amount): bool
    {
        if ($this->current_cargo < $amount) {
            return false;
        }

        $this->current_cargo -= $amount;
        $this->save();

        return true;
    }

    // =========================================================================
    // HIDDEN CARGO (Smuggling Ships)
    // =========================================================================

    /**
     * Check if this ship has hidden cargo capability
     */
    public function hasHiddenHold(): bool
    {
        return ($this->hidden_hold_capacity ?? 0) > 0;
    }

    /**
     * Check if ship can add hidden cargo
     */
    public function canAddHiddenCargo(int $amount): bool
    {
        if (! $this->hasHiddenHold()) {
            return false;
        }

        return ($this->hidden_cargo + $amount) <= $this->hidden_hold_capacity;
    }

    /**
     * Add cargo to hidden hold
     */
    public function addHiddenCargo(int $amount): bool
    {
        if (! $this->canAddHiddenCargo($amount)) {
            return false;
        }

        $this->hidden_cargo += $amount;
        $this->save();

        return true;
    }

    /**
     * Remove cargo from hidden hold
     */
    public function removeHiddenCargo(int $amount): bool
    {
        if ($this->hidden_cargo < $amount) {
            return false;
        }

        $this->hidden_cargo -= $amount;
        $this->save();

        return true;
    }

    /**
     * Get total cargo capacity (effective + hidden)
     */
    public function getTotalCargoCapacity(): int
    {
        return $this->getEffectiveCargoHold() + ($this->hidden_hold_capacity ?? 0);
    }

    /**
     * Get total current cargo (regular + hidden)
     */
    public function getTotalCurrentCargo(): int
    {
        return $this->current_cargo + ($this->hidden_cargo ?? 0);
    }

    // =========================================================================
    // COLONISTS (Colony Ships)
    // =========================================================================

    /**
     * Check if this ship can carry colonists
     */
    public function isColonyShip(): bool
    {
        return ($this->colonist_capacity ?? 0) > 0;
    }

    /**
     * Check if ship can board more colonists
     */
    public function canBoardColonists(int $amount): bool
    {
        if (! $this->isColonyShip()) {
            return false;
        }

        return ($this->current_colonists + $amount) <= $this->colonist_capacity;
    }

    /**
     * Board colonists onto the ship
     */
    public function boardColonists(int $amount): bool
    {
        if (! $this->canBoardColonists($amount)) {
            return false;
        }

        $this->current_colonists += $amount;
        $this->save();

        return true;
    }

    /**
     * Disembark colonists at a colony
     */
    public function disembarkColonists(int $amount): bool
    {
        if ($this->current_colonists < $amount) {
            return false;
        }

        $this->current_colonists -= $amount;
        $this->save();

        return true;
    }

    /**
     * Disembark all colonists
     */
    public function disembarkAllColonists(): int
    {
        $colonists = $this->current_colonists;
        $this->current_colonists = 0;
        $this->save();

        return $colonists;
    }

    // =========================================================================
    // SHIP VARIATION DISPLAY
    // =========================================================================

    /**
     * Get a summary of this ship's variation traits
     */
    public function getVariationSummary(): array
    {
        $summary = [];

        if (($this->fuel_regen_modifier ?? 1.0) != 1.0) {
            $percent = round(($this->fuel_regen_modifier - 1.0) * 100);
            $summary['fuel_regen'] = ($percent > 0 ? '+' : '').$percent.'%';
        }

        if (($this->fuel_consumption_modifier ?? 1.0) != 1.0) {
            $percent = round(($this->fuel_consumption_modifier - 1.0) * 100);
            $summary['fuel_consumption'] = ($percent > 0 ? '+' : '').$percent.'%';
        }

        if (($this->speed_modifier ?? 1.0) != 1.0) {
            $percent = round(($this->speed_modifier - 1.0) * 100);
            $summary['speed'] = ($percent > 0 ? '+' : '').$percent.'%';
        }

        return $summary;
    }

    /**
     * Check if this ship has any variation traits
     */
    public function hasVariations(): bool
    {
        return ! empty($this->variation_traits);
    }
}
