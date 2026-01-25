<?php

namespace App\Models;

use App\Enums\Defense\SystemDefenseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Pre-built defenses at core systems (not player-built).
 *
 * These fortifications protect civilized systems in the core region.
 */
class SystemDefense extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'poi_id',
        'defense_type',
        'level',
        'quantity',
        'health',
        'max_health',
        'is_active',
        'attributes',
    ];

    protected $casts = [
        'defense_type' => SystemDefenseType::class,
        'level' => 'integer',
        'quantity' => 'integer',
        'health' => 'integer',
        'max_health' => 'integer',
        'is_active' => 'boolean',
        'attributes' => 'array',
    ];

    /**
     * Register model event handlers used during model lifecycle initialization.
     *
     * Ensures a UUID is assigned when a SystemDefense is created and, if max_health
     * is not provided but health is set, initializes max_health from health.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($defense) {
            if (empty($defense->uuid)) {
                $defense->uuid = Str::uuid();
            }

            // Set max_health from health if not specified
            if (empty($defense->max_health) && ! empty($defense->health)) {
                $defense->max_health = $defense->health;
            }
        });
    }

    /**
     * Get the PointOfInterest this SystemDefense belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The associated PointOfInterest relation (foreign key: `poi_id`).
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Calculate the total damage this defense currently deals.
     *
     * @return int The computed damage considering the defense's operational state, base damage, level multiplier (15% per level above 1), quantity, and any `attributes['damage_multiplier']`.
     */
    public function calculateDamage(): int
    {
        if (! $this->isOperational()) {
            return 0;
        }

        $baseDamage = $this->defense_type->getBaseDamage();
        $levelMultiplier = 1 + (($this->level - 1) * 0.15);  // 15% per level
        $damage = (int) ($baseDamage * $levelMultiplier * $this->quantity);

        // Apply any special damage modifiers from attributes
        if (isset($this->attributes['damage_multiplier'])) {
            $damage = (int) ($damage * $this->attributes['damage_multiplier']);
        }

        return $damage;
    }

    /**
         * Compute total damage dealt by fighters stationed in this defense.
         *
         * Uses `attributes['fighter_count']` (default 0) and `attributes['fighter_damage']` (default 25),
         * and applies a level multiplier of 1 + (level - 1) * 0.1. Returns 0 if the defense is not a fighter port
         * or is not operational.
         *
         * @return int Total fighter damage as an integer.
         */
    public function calculateFighterDamage(): int
    {
        if ($this->defense_type !== SystemDefenseType::FIGHTER_PORT || ! $this->isOperational()) {
            return 0;
        }

        $fighterCount = $this->attributes['fighter_count'] ?? 0;
        $damagePerFighter = $this->attributes['fighter_damage'] ?? 25;
        $levelMultiplier = 1 + (($this->level - 1) * 0.1);  // 10% per level

        return (int) ($fighterCount * $damagePerFighter * $levelMultiplier);
    }

    /**
     * Apply incoming damage to this defense and persist the updated health.
     *
     * If the defense is a planetary shield, the incoming damage is reduced by the
     * `damage_reduction` attribute (defaults to 0.5). Health is decreased but not
     * below zero and the model is saved.
     *
     * @return array{
     *     damage_taken: int,       // actual health subtracted
     *     damage_absorbed: int,    // portion of incoming damage prevented or absorbed
     *     remaining_health: int,   // health after applying damage
     *     destroyed: bool          // true if health is zero or less
     * }
     */
    public function takeDamage(int $damage): array
    {
        $oldHealth = $this->health;

        // Apply shield damage reduction if this is a shield
        if ($this->defense_type === SystemDefenseType::PLANETARY_SHIELD) {
            $damageReduction = $this->attributes['damage_reduction'] ?? 0.5;
            $damage = (int) ($damage * (1 - $damageReduction));
        }

        $this->health = max(0, $this->health - $damage);
        $this->save();

        $destroyed = $this->health <= 0;

        return [
            'damage_taken' => $oldHealth - $this->health,
            'damage_absorbed' => $damage - ($oldHealth - $this->health),
            'remaining_health' => $this->health,
            'destroyed' => $destroyed,
        ];
    }

    /**
     * Determine whether the defense is operational.
     *
     * @return bool `true` if the defense is active and its health is greater than zero, `false` otherwise.
     */
    public function isOperational(): bool
    {
        return $this->is_active && $this->health > 0;
    }

    /**
     * Determine whether the defense is eligible for repairs.
     *
     * @return bool `true` if health is greater than 0 and less than max_health, `false` otherwise.
     */
    public function canBeRepaired(): bool
    {
        return $this->health < $this->max_health && $this->health > 0;
    }

    /**
     * Restore the defense's health by the given amount, not exceeding its max_health.
     *
     * @param int $amount The amount of health to restore.
     * @return int The actual amount of health restored.
     */
    public function repair(int $amount): int
    {
        $oldHealth = $this->health;
        $this->health = min($this->max_health, $this->health + $amount);
        $this->save();

        return $this->health - $oldHealth;
    }

    /**
     * Restores shield health for planetary shields when active.
     *
     * Uses the defense's `recharge_rate` attribute (default 100) to determine how much health to restore.
     *
     * @return int The actual amount of health restored.
     */
    public function regenerateShield(): int
    {
        if ($this->defense_type !== SystemDefenseType::PLANETARY_SHIELD || ! $this->is_active) {
            return 0;
        }

        $rechargeRate = $this->attributes['recharge_rate'] ?? 100;

        return $this->repair($rechargeRate);
    }

    /**
     * Decrease the stored fighter count for a fighter port by a given number.
     *
     * If the defense is not a fighter port, no change is made and zero is returned.
     *
     * @param int $count Number of fighters to remove.
     * @return int The actual number of fighters removed (may be less than `$count` if not enough fighters were available).
     */
    public function loseFighters(int $count): int
    {
        if ($this->defense_type !== SystemDefenseType::FIGHTER_PORT) {
            return 0;
        }

        $currentCount = $this->attributes['fighter_count'] ?? 0;
        $newCount = max(0, $currentCount - $count);

        $attributes = $this->attributes ?? [];
        $attributes['fighter_count'] = $newCount;
        $this->attributes = $attributes;
        $this->save();

        return $currentCount - $newCount;
    }

    /**
     * Current health expressed as a percentage of max health.
     *
     * @return float The health percentage (0–100) rounded to one decimal place; returns 0 if max health is less than or equal to 0.
     */
    public function getHealthPercentage(): float
    {
        if ($this->max_health <= 0) {
            return 0;
        }

        return round(($this->health / $this->max_health) * 100, 1);
    }

    /**
         * Restrict the query to defenses that are active and have health greater than zero.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query The query builder instance.
         * @return \Illuminate\Database\Eloquent\Builder The query builder filtered to active defenses with health > 0.
         */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('health', '>', 0);
    }

    /**
         * Filter the query to defenses matching the provided defense type.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query The Eloquent query builder instance.
         * @param SystemDefenseType $type The defense type to filter by.
         * @return \Illuminate\Database\Eloquent\Builder The modified query builder.
         */
    public function scopeOfType($query, SystemDefenseType $type)
    {
        return $query->where('defense_type', $type);
    }
}