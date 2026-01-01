<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ColonyBuilding extends Model
{
    protected $fillable = [
        'uuid',
        'colony_id',
        'building_type',
        'level',
        'status',
        'construction_progress',
        'construction_cost_credits',
        'construction_cost_minerals',
        'construction_cost_population',
        'construction_started_at',
        'construction_completed_at',
        'effects',
    ];

    protected $casts = [
        'level' => 'integer',
        'construction_progress' => 'integer',
        'construction_cost_credits' => 'integer',
        'construction_cost_minerals' => 'integer',
        'construction_cost_population' => 'integer',
        'construction_started_at' => 'datetime',
        'construction_completed_at' => 'datetime',
        'effects' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($building) {
            if (empty($building->uuid)) {
                $building->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the colony this building belongs to
     */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /**
     * Get building type display name
     */
    public function getTypeDisplay(): string
    {
        return match($this->building_type) {
            'shipyard' => '🚀 Shipyard',
            'orbital_defense' => '🛡️ Orbital Defense',
            'trade_station' => '🏪 Trade Station',
            'mining_facility' => '⛏️ Mining Facility',
            'hydroponics' => '🌱 Hydroponics Bay',
            'hab_module' => '🏠 Habitat Module',
            default => ucfirst(str_replace('_', ' ', $this->building_type)),
        };
    }

    /**
     * Get status display
     */
    public function getStatusDisplay(): string
    {
        return match($this->status) {
            'constructing' => '🏗️ Under Construction',
            'operational' => '✅ Operational',
            'damaged' => '⚠️ Damaged',
            'destroyed' => '❌ Destroyed',
            default => $this->status,
        };
    }

    /**
     * Advance construction progress
     */
    public function advanceConstruction(int $amount): void
    {
        $this->construction_progress = min(100, $this->construction_progress + $amount);

        if ($this->construction_progress >= 100) {
            $this->status = 'operational';
            $this->construction_completed_at = now();
        }

        $this->save();
    }

    /**
     * Get building costs based on type and level
     */
    public static function getBuildingCosts(string $buildingType, int $level = 1): array
    {
        $baseCosts = match($buildingType) {
            'shipyard' => ['credits' => 50000, 'minerals' => 10000, 'population' => 100],
            'orbital_defense' => ['credits' => 30000, 'minerals' => 15000, 'population' => 50],
            'trade_station' => ['credits' => 20000, 'minerals' => 5000, 'population' => 30],
            'mining_facility' => ['credits' => 15000, 'minerals' => 8000, 'population' => 50],
            'hydroponics' => ['credits' => 10000, 'minerals' => 3000, 'population' => 20],
            'hab_module' => ['credits' => 8000, 'minerals' => 4000, 'population' => 10],
            default => ['credits' => 10000, 'minerals' => 5000, 'population' => 20],
        };

        // Scale costs by level
        $multiplier = 1 + (($level - 1) * 0.5);

        return [
            'credits' => (int) ($baseCosts['credits'] * $multiplier),
            'minerals' => (int) ($baseCosts['minerals'] * $multiplier),
            'population' => (int) ($baseCosts['population'] * $multiplier),
        ];
    }

    /**
     * Get building effects based on type and level
     */
    public static function getBuildingEffects(string $buildingType, int $level = 1): array
    {
        $baseEffects = match($buildingType) {
            'shipyard' => ['ship_production_speed' => 10],
            'orbital_defense' => ['defense_rating' => 50],
            'trade_station' => ['credits_per_cycle' => 500],
            'mining_facility' => ['mineral_production' => 100],
            'hydroponics' => ['food_production' => 200],
            'hab_module' => ['max_population_bonus' => 500],
            default => [],
        };

        // Scale effects by level
        $multiplier = 1 + (($level - 1) * 0.3);

        $scaledEffects = [];
        foreach ($baseEffects as $key => $value) {
            $scaledEffects[$key] = (int) ($value * $multiplier);
        }

        return $scaledEffects;
    }

    /**
     * Upgrade building to next level
     */
    public function upgrade(): bool
    {
        if ($this->level >= 5) {
            return false; // Max level
        }

        $newLevel = $this->level + 1;
        $costs = self::getBuildingCosts($this->building_type, $newLevel);
        $effects = self::getBuildingEffects($this->building_type, $newLevel);

        // Check if colony/player has resources
        $colony = $this->colony;
        $player = $colony->player;

        if ($player->credits < $costs['credits']) {
            return false;
        }

        // Deduct costs
        $player->credits -= $costs['credits'];
        $player->save();

        // Upgrade
        $this->level = $newLevel;
        $this->effects = $effects;
        $this->construction_progress = 0;
        $this->status = 'constructing';
        $this->construction_cost_credits = $costs['credits'];
        $this->construction_cost_minerals = $costs['minerals'];
        $this->construction_cost_population = $costs['population'];
        $this->save();

        return true;
    }
}
