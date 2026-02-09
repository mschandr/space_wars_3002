<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PlayerShip extends Model
{
    use HasFactory;

    const FUEL_REGEN_RATE = 30; // seconds per fuel point

    protected $fillable = [
        'uuid',
        'player_id',
        'ship_id',
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

        static::creating(function ($playerShip) {
            if (empty($playerShip->uuid)) {
                $playerShip->uuid = Str::uuid();
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
     * Get installed weapon components.
     *
     * Note: Named weaponComponents() to avoid shadowing the 'weapons' integer attribute.
     */
    public function weaponComponents(): HasMany
    {
        return $this->hasMany(PlayerShipComponent::class)->where('slot_type', 'weapon_slot');
    }

    /**
     * Get installed utility components.
     *
     * Note: Named utilityComponents() for consistency with weaponComponents().
     */
    public function utilityComponents(): HasMany
    {
        return $this->hasMany(PlayerShipComponent::class)->where('slot_type', 'utility_slot');
    }

    /**
     * Get number of available weapon slots
     */
    public function getAvailableWeaponSlots(): int
    {
        $usedSlots = $this->components()
            ->where('slot_type', 'weapon_slot')
            ->with('component')
            ->get()
            ->sum(fn ($c) => $c->component->slots_required ?? 1);

        return max(0, ($this->weapon_slots ?? 0) - $usedSlots);
    }

    /**
     * Get number of available utility slots
     */
    public function getAvailableUtilitySlots(): int
    {
        $usedSlots = $this->components()
            ->where('slot_type', 'utility_slot')
            ->with('component')
            ->get()
            ->sum(fn ($c) => $c->component->slots_required ?? 1);

        return max(0, ($this->utility_slots ?? 0) - $usedSlots);
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
     */
    public function regenerateFuel(): void
    {
        if ($this->current_fuel >= $this->max_fuel) {
            return;
        }

        $now = Carbon::now();
        $lastUpdate = Carbon::parse($this->fuel_last_updated_at);
        $secondsElapsed = (int) abs($now->diffInSeconds($lastUpdate));

        // Apply fuel regen modifier (higher modifier = faster regen = lower seconds per fuel)
        $regenModifier = $this->fuel_regen_modifier ?? 1.0;
        $effectiveRegenRate = max(5, (int) (self::FUEL_REGEN_RATE / $regenModifier));

        $fuelToRegenerate = (int) floor($secondsElapsed / $effectiveRegenRate);

        if ($fuelToRegenerate > 0) {
            $this->current_fuel = min($this->max_fuel, $this->current_fuel + $fuelToRegenerate);
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

        if ($this->current_fuel >= $this->max_fuel) {
            return 0;
        }

        $fuelNeeded = $this->max_fuel - $this->current_fuel;

        return $fuelNeeded * self::FUEL_REGEN_RATE;
    }

    /**
     * Take damage
     */
    public function takeDamage(int $damage): void
    {
        $this->hull = max(0, $this->hull - $damage);

        if ($this->hull <= 0) {
            $this->status = 'destroyed';
        } elseif ($this->hull < $this->max_hull * 0.3) {
            $this->status = 'damaged';
        }

        $this->save();
    }

    /**
     * Repair hull
     */
    public function repair(int $amount): void
    {
        $this->hull = min($this->max_hull, $this->hull + $amount);

        if ($this->hull > $this->max_hull * 0.3) {
            $this->status = 'operational';
        }

        $this->save();
    }

    /**
     * Check if ship can carry more cargo
     */
    public function canAddCargo(int $amount): bool
    {
        return ($this->current_cargo + $amount) <= $this->cargo_hold;
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
     * Get total cargo capacity (regular + hidden)
     */
    public function getTotalCargoCapacity(): int
    {
        return $this->cargo_hold + ($this->hidden_hold_capacity ?? 0);
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
