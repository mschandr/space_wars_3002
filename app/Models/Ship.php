<?php

namespace App\Models;

use App\Enums\SlotType;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ship extends Model
{
    use HasFactory, HasUuid;

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
        'engine_slots',
        'reactor_slots',
        'hull_plating_slots',
        'shield_slots',
        'sensor_slots',
        'cargo_module_slots',
        'size_class',
        'rarity',
        'requirements',
        'attributes',
        'sales_pitches',
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
        'engine_slots' => 'integer',
        'reactor_slots' => 'integer',
        'hull_plating_slots' => 'integer',
        'shield_slots' => 'integer',
        'sensor_slots' => 'integer',
        'cargo_module_slots' => 'integer',
        'requirements' => 'array',
        'attributes' => 'array',
        'sales_pitches' => 'array',
        'is_available' => 'boolean',
    ];

    /**
     * Get the slot count for a given slot type.
     */
    public function getSlotCount(SlotType $type): int
    {
        return (int) ($this->{$type->slotColumn()} ?? 1);
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
     * Get a random sales pitch for this ship from the shipyard owner.
     * Falls back to generic tier-based pitches if none are defined.
     */
    public function getSalesPitch(?bool $buyerOwnsShip = true): ?string
    {
        // Hand-written pitches take priority
        $pitches = $this->sales_pitches ?? [];

        if (! empty($pitches)) {
            return $pitches[array_rand($pitches)];
        }

        // Fall back to generic pitches by price tier
        $fallbacks = config('game_config.ships.sales_pitch_fallbacks', []);

        $tier = match (true) {
            $this->base_price <= 0 && ! $buyerOwnsShip => 'free_first_ship',
            $this->base_price <= 0 => 'free',
            $this->base_price < 20000 => 'budget',
            $this->base_price < 60000 => 'midrange',
            $this->base_price < 200000 => 'premium',
            default => 'luxury',
        };

        $tierPitches = $fallbacks[$tier] ?? $fallbacks['midrange'] ?? [];

        if (empty($tierPitches)) {
            return null;
        }

        return $tierPitches[array_rand($tierPitches)];
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
            if (! isset($playerStats[$requirement]) || $playerStats[$requirement] < $value) {
                return false;
            }
        }

        return true;
    }
}
