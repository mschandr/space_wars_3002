<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Component Instance - a specific component installed on a player's ship.
 *
 * Each instance has:
 * - A blueprint (ShipComponent) defining what it is
 * - A slot (weapon_slot or utility_slot) and index
 * - Condition (0-100, degrades with use)
 * - Ammo (for weapons that use ammunition)
 */
class PlayerShipComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_ship_id',
        'ship_component_id',
        'slot_type',
        'slot_index',
        'condition',
        'ammo',
        'max_ammo',
        'is_active',
    ];

    protected $casts = [
        'slot_index' => 'integer',
        'condition' => 'integer',
        'ammo' => 'integer',
        'max_ammo' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the ship this component is installed on
     */
    public function playerShip(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class);
    }

    /**
     * Get the component blueprint
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(ShipComponent::class, 'ship_component_id');
    }

    /**
     * Check if this component is damaged
     */
    public function isDamaged(): bool
    {
        return $this->condition < 100;
    }

    /**
     * Check if this component is broken (unusable)
     */
    public function isBroken(): bool
    {
        return $this->condition <= 0;
    }

    /**
     * Check if this component needs ammo and is out
     */
    public function needsAmmo(): bool
    {
        return $this->max_ammo !== null && $this->ammo <= 0;
    }

    /**
     * Reload ammo (returns amount loaded)
     */
    public function reload(int $amount): int
    {
        if ($this->max_ammo === null) {
            return 0;
        }

        $spaceAvailable = $this->max_ammo - $this->ammo;
        $toLoad = min($amount, $spaceAvailable);

        $this->ammo += $toLoad;
        $this->save();

        return $toLoad;
    }

    /**
     * Use ammo (returns true if successful)
     */
    public function useAmmo(int $amount = 1): bool
    {
        if ($this->max_ammo === null) {
            return true; // No ammo needed
        }

        if ($this->ammo < $amount) {
            return false;
        }

        $this->ammo -= $amount;
        $this->save();

        return true;
    }

    /**
     * Damage this component
     */
    public function damage(int $amount): void
    {
        $this->condition = max(0, $this->condition - $amount);
        $this->save();
    }

    /**
     * Repair this component
     */
    public function repair(int $amount): void
    {
        $this->condition = min(100, $this->condition + $amount);
        $this->save();
    }

    /**
     * Get the effective value of a stat from this component
     * (reduced by condition percentage)
     */
    public function getEffectiveEffect(string $stat): mixed
    {
        $baseEffect = $this->component->getEffect($stat);

        if ($baseEffect === null || ! is_numeric($baseEffect)) {
            return $baseEffect;
        }

        // Reduce effectiveness based on condition
        $conditionMultiplier = $this->condition / 100;

        return $baseEffect * $conditionMultiplier;
    }
}
