<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NpcShip extends Model
{
    use HasFactory;

    const FUEL_REGEN_RATE = 30; // seconds per fuel point

    protected $fillable = [
        'uuid',
        'npc_id',
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
        'current_cargo',
        'is_active',
        'status',
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
        'current_cargo' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Register model event handlers and ensure a UUID is assigned when creating.
     *
     * Registers a creating event listener that sets the model's `uuid` to a newly generated UUID if it is empty before the model is persisted.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($npcShip) {
            if (empty($npcShip->uuid)) {
                $npcShip->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the NPC that owns this ship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The relationship linking this ship to its owning NPC.
     */
    public function npc(): BelongsTo
    {
        return $this->belongsTo(Npc::class);
    }

    /**
     * Get the associated Ship model for this NPC ship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The associated Ship model relation.
     */
    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    /**
     * Get the cargo items associated with this NPC ship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Relation for the related NpcCargo models.
     */
    public function cargo(): HasMany
    {
        return $this->hasMany(NpcCargo::class);
    }

    /**
     * Regenerates the ship's fuel over time and persists any change.
     *
     * If the ship's current fuel is below max, increases current_fuel by the amount
     * accrued since fuel_last_updated_at according to FUEL_REGEN_RATE, caps at max_fuel,
     * updates fuel_last_updated_at to reflect leftover time toward the next regen tick,
     * and saves the model if fuel was added. Does nothing when current_fuel is already at or above max_fuel.
     */
    public function regenerateFuel(): void
    {
        if ($this->current_fuel >= $this->max_fuel) {
            return;
        }

        $now = Carbon::now();
        $lastUpdate = Carbon::parse($this->fuel_last_updated_at);
        $secondsElapsed = (int) abs($now->diffInSeconds($lastUpdate));

        $fuelToRegenerate = (int) floor($secondsElapsed / self::FUEL_REGEN_RATE);

        if ($fuelToRegenerate > 0) {
            $this->current_fuel = min($this->max_fuel, $this->current_fuel + $fuelToRegenerate);
            $this->fuel_last_updated_at = $now->subSeconds($secondsElapsed % self::FUEL_REGEN_RATE);
            $this->save();
        }
    }

    /**
     * Retrieve the ship's current fuel after applying regeneration.
     *
     * @return int The current fuel amount after regeneration.
     */
    public function getCurrentFuel(): int
    {
        $this->regenerateFuel();

        return $this->current_fuel;
    }

    /**
     * Attempt to deduct fuel for a travel action, scaled by the ship's warp drive.
     *
     * The actual fuel consumed is floor($amount / $this->warp_drive) with a minimum of 1. If sufficient fuel exists the model's `current_fuel` and `fuel_last_updated_at` are updated and the model is saved.
     *
     * @param int $amount The requested fuel amount before warp-drive scaling.
     * @return bool `true` if fuel was deducted and the model saved, `false` if there was insufficient fuel.
     */
    public function consumeFuel(int $amount): bool
    {
        $this->regenerateFuel();

        $effectiveConsumption = max(1, (int) floor($amount / $this->warp_drive));

        if ($this->current_fuel < $effectiveConsumption) {
            return false;
        }

        $this->current_fuel -= $effectiveConsumption;
        $this->fuel_last_updated_at = Carbon::now();
        $this->save();

        return true;
    }

    /**
     * Apply damage to the ship's hull and update its status accordingly.
     *
     * Reduces hull by the given damage (not below 0), sets status to `destroyed` when hull
     * is 0 or less, or `damaged` when hull is below 30% of `max_hull`, and persists the model.
     *
     * @param int $damage The amount of hull damage to apply.
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
         * Restore hull points by a specified amount, up to the ship's maximum hull.
         *
         * If the resulting hull is greater than 30% of `max_hull`, sets `status` to `'operational'`.
         * Persists the model after applying changes.
         *
         * @param int $amount The amount of hull to restore.
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
         * Determine whether the ship can accept additional cargo of the given amount.
         *
         * @param int $amount Amount of cargo to add.
         * @return bool `true` if adding `$amount` does not exceed the ship's cargo hold capacity, `false` otherwise.
         */
    public function canAddCargo(int $amount): bool
    {
        return ($this->current_cargo + $amount) <= $this->cargo_hold;
    }

    /**
     * Attempt to add cargo to the ship and persist the change.
     *
     * @param int $amount The quantity of cargo to add.
     * @return bool `true` if the cargo was added and saved, `false` if there was insufficient capacity.
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
     * Remove a specified number of cargo units from the ship.
     *
     * @param int $amount The number of cargo units to remove.
     * @return bool `true` if the cargo was removed, `false` if there was insufficient cargo.
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
}