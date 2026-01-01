<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ship extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'name',
        'class',
        'description',
        'base_price',
        'cargo_capacity',
        'speed',
        'hull_strength',
        'shield_strength',
        'weapon_slots',
        'utility_slots',
        'rarity',
        'requirements',
        'attributes',
        'is_available',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'cargo_capacity' => 'integer',
        'speed' => 'integer',
        'hull_strength' => 'integer',
        'shield_strength' => 'integer',
        'weapon_slots' => 'integer',
        'utility_slots' => 'integer',
        'requirements' => 'array',
        'attributes' => 'array',
        'is_available' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ship) {
            if (empty($ship->uuid)) {
                $ship->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the ship's overall combat rating
     */
    public function getCombatRating(): int
    {
        return ($this->hull_strength + $this->shield_strength + ($this->weapon_slots * 50)) / 3;
    }

    /**
     * Get the ship's utility score
     */
    public function getUtilityScore(): int
    {
        return $this->cargo_capacity + $this->speed + ($this->utility_slots * 25);
    }

    /**
     * Check if ship meets requirements
     */
    public function meetsRequirements(array $playerStats): bool
    {
        if (empty($this->requirements)) {
            return true;
        }

        foreach ($this->requirements as $requirement => $value) {
            if (!isset($playerStats[$requirement]) || $playerStats[$requirement] < $value) {
                return false;
            }
        }

        return true;
    }
}
