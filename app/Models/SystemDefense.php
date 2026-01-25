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
     * Relationships
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Calculate damage output for this defense.
     * Takes into account level, quantity, and defense type.
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
     * Calculate fighter damage (for fighter ports).
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
     * Apply damage to this defense.
     *
     * @return array Result of the damage application
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
     * Check if this defense is operational.
     */
    public function isOperational(): bool
    {
        return $this->is_active && $this->health > 0;
    }

    /**
     * Check if this defense can be repaired.
     */
    public function canBeRepaired(): bool
    {
        return $this->health < $this->max_health && $this->health > 0;
    }

    /**
     * Repair this defense.
     *
     * @param  int  $amount  Amount of health to restore
     */
    public function repair(int $amount): int
    {
        $oldHealth = $this->health;
        $this->health = min($this->max_health, $this->health + $amount);
        $this->save();

        return $this->health - $oldHealth;
    }

    /**
     * Regenerate shields (for planetary shields).
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
     * Reduce fighter count after combat losses.
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
     * Get health percentage.
     */
    public function getHealthPercentage(): float
    {
        if ($this->max_health <= 0) {
            return 0;
        }

        return round(($this->health / $this->max_health) * 100, 1);
    }

    /**
     * Query scope for active defenses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('health', '>', 0);
    }

    /**
     * Query scope for defenses of a specific type.
     */
    public function scopeOfType($query, SystemDefenseType $type)
    {
        return $query->where('defense_type', $type);
    }
}
