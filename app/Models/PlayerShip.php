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
     * Calculate and update fuel based on time elapsed
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
     * Get current fuel after regeneration
     */
    public function getCurrentFuel(): int
    {
        $this->regenerateFuel();

        return $this->current_fuel;
    }

    /**
     * Consume fuel for travel
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
}
