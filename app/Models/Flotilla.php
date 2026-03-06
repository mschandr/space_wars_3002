<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flotilla extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['player_id', 'name', 'flagship_ship_id'];

    protected $with = ['flagship', 'ships'];

    /**
     * Relationship: Flotilla belongs to a player
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Relationship: Flagship ship
     */
    public function flagship(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class, 'flagship_ship_id');
    }

    /**
     * Relationship: All ships in this flotilla
     */
    public function ships(): HasMany
    {
        return $this->hasMany(PlayerShip::class, 'flotilla_id');
    }

    /**
     * Get the number of ships in this flotilla
     */
    public function shipCount(): int
    {
        return $this->ships()->count();
    }

    /**
     * Check if flotilla is at maximum capacity
     */
    public function isFull(): bool
    {
        return $this->shipCount() >= config('game_config.flotilla.max_ships');
    }

    /**
     * Check if a ship can be added to this flotilla
     */
    public function canAddShip(): bool
    {
        return !$this->isFull();
    }

    /**
     * Get total hull points across all ships
     */
    public function totalHull(): int
    {
        return $this->ships()->sum('hull');
    }

    /**
     * Get total cargo hold capacity across all ships
     */
    public function totalCargoHold(): int
    {
        return $this->ships()->sum('cargo_hold');
    }

    /**
     * Get the ship with the lowest hull (weakest)
     */
    public function lowestHull(): ?PlayerShip
    {
        return $this->ships()->orderBy('hull')->first();
    }

    /**
     * Get the ship with the lowest warp drive (slowest)
     */
    public function slowestShip(): ?PlayerShip
    {
        return $this->ships()->orderBy('warp_drive')->first();
    }

    /**
     * Get the ship with the highest hull (toughest)
     */
    public function highestHull(): ?PlayerShip
    {
        return $this->ships()->orderByDesc('hull')->first();
    }

    /**
     * Get total weapon damage from all ships in the flotilla
     * (Used for combat calculations)
     */
    public function getTotalWeaponDamage(): int
    {
        $totalDamage = 0;

        foreach ($this->ships as $ship) {
            // Calculate base damage from ship's weapons
            // weapon_power * weapon_level gives total damage per ship
            // This assumes ships have weapon_power and weapon_level attributes
            $baseDamage = ($ship->weapon_power ?? 0) * ($ship->weapon_level ?? 0);
            $totalDamage += $baseDamage;
        }

        return $totalDamage;
    }

    /**
     * Get the weakest ship (lowest hull) for targeting purposes
     * Used in combat when pirates target flotilla
     */
    public function getWeakestShip(): ?PlayerShip
    {
        return $this->lowestHull();
    }

    /**
     * Check if all ships in the flotilla are at the same POI
     */
    public function areAllShipsAtSamePoi(): bool
    {
        if ($this->ships()->count() === 0) {
            return true;
        }

        $poiId = $this->ships()->first()->current_poi_id;

        return $this->ships()
            ->where('current_poi_id', '!=', $poiId)
            ->count() === 0;
    }

    /**
     * Get current location POI if all ships are at same location
     */
    public function getCurrentLocation(): ?PointOfInterest
    {
        if (!$this->areAllShipsAtSamePoi()) {
            return null;
        }

        $firstShip = $this->ships()->first();
        if (!$firstShip) {
            return null;
        }

        return $firstShip->currentLocation;
    }

    /**
     * Get available cargo hold space in the flotilla
     */
    public function getAvailableCargoSpace(): int
    {
        $totalSpace = $this->totalCargoHold();
        $usedSpace = 0;

        foreach ($this->ships as $ship) {
            // Assuming ship has a way to get used cargo space
            $usedSpace += $ship->getUsedCargoSpace();
        }

        return max(0, $totalSpace - $usedSpace);
    }

    /**
     * Scope: Only active flotillas
     */
    public function scopeActive($query)
    {
        return $query->whereNotNull('id');
    }

    /**
     * Scope: Filter by player
     */
    public function scopeByPlayer($query, Player $player)
    {
        return $query->where('player_id', $player->id);
    }
}
