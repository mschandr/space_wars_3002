<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColonyBuilding extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'colony_id',
        'building_type',
        'required_stage',
        'level',
        'status',
        'construction_progress',
        'construction_cost_credits',
        'construction_cost_minerals',
        'construction_cost_population',
        'construction_started_at',
        'construction_completed_at',
        'effects',
        'credits_per_cycle',
        'quantium_per_cycle',
        'food_per_cycle',
        'minerals_per_cycle',
        'credits_generated_per_cycle',
        'last_cycle_at',
    ];

    protected $casts = [
        'required_stage' => 'integer',
        'level' => 'integer',
        'construction_progress' => 'integer',
        'construction_cost_credits' => 'integer',
        'construction_cost_minerals' => 'integer',
        'construction_cost_population' => 'integer',
        'construction_started_at' => 'datetime',
        'construction_completed_at' => 'datetime',
        'effects' => 'array',
        'credits_per_cycle' => 'integer',
        'quantium_per_cycle' => 'integer',
        'food_per_cycle' => 'integer',
        'minerals_per_cycle' => 'integer',
        'credits_generated_per_cycle' => 'integer',
        'last_cycle_at' => 'datetime',
    ];

    /**
     * Get the colony this building belongs to
     */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /**
     * Get comprehensive building definitions with stages, costs, and effects
     */
    public static function getBuildingDefinitions(): array
    {
        return [
            'hab_module' => [
                'name' => '🏠 Habitat Module',
                'description' => 'Basic housing for colonists',
                'required_stage' => 1,
                'base_cost' => ['credits' => 5000, 'minerals' => 2000, 'population' => 10],
                'effects' => ['max_population_bonus' => 500],
                'operating_costs' => ['credits' => 0, 'quantium' => 0, 'food' => 5, 'minerals' => 0],
                'income' => 0,
            ],
            'hydroponics' => [
                'name' => '🌱 Hydroponics Bay',
                'description' => 'Grows plant-based food',
                'required_stage' => 1,
                'base_cost' => ['credits' => 8000, 'minerals' => 3000, 'population' => 15],
                'effects' => ['food_production' => 200],
                'operating_costs' => ['credits' => 10, 'quantium' => 0, 'food' => 0, 'minerals' => 5],
                'income' => 0,
            ],
            'synth_lab' => [
                'name' => '🥩 Synth Lab',
                'description' => 'Produces synthetic meat - biologically compatible food',
                'required_stage' => 3,
                'base_cost' => ['credits' => 15000, 'minerals' => 5000, 'population' => 25],
                'effects' => ['food_production' => 300],
                'operating_costs' => ['credits' => 20, 'quantium' => 0, 'food' => 0, 'minerals' => 10],
                'income' => 0,
            ],
            'warp_gate' => [
                'name' => '🌀 Warp Gate',
                'description' => 'Massive infrastructure generating passive income from gate traffic',
                'required_stage' => 5,
                'base_cost' => ['credits' => 500000, 'minerals' => 100000, 'population' => 200],
                'effects' => ['gate_operational' => true],
                'operating_costs' => ['credits' => 0, 'quantium' => 1, 'food' => 0, 'minerals' => 0],
                'income' => 600, // Credits per hour
            ],
            'orbital_mining' => [
                'name' => '⛏️ Orbital Mining Facility',
                'description' => 'Mines resources from gas giants and asteroid fields',
                'required_stage' => 6,
                'base_cost' => ['credits' => 40000, 'minerals' => 15000, 'population' => 50],
                'effects' => ['mineral_production' => 100, 'quantium_extraction' => true],
                'operating_costs' => ['credits' => 50, 'quantium' => 0, 'food' => 0, 'minerals' => 0],
                'income' => 0,
            ],
            'orbital_defense' => [
                'name' => '🛡️ Orbital Defense Platform',
                'description' => 'Defends colony from pirate attacks',
                'required_stage' => 7,
                'base_cost' => ['credits' => 75000, 'minerals' => 25000, 'population' => 75],
                'effects' => ['defense_rating' => 100],
                'operating_costs' => ['credits' => 100, 'quantium' => 0, 'food' => 0, 'minerals' => 5],
                'income' => 0,
            ],
            'orbital_sensor' => [
                'name' => '📡 Orbital Sensor Array',
                'description' => 'Detects pirates and improves resource scanning',
                'required_stage' => 8,
                'base_cost' => ['credits' => 50000, 'minerals' => 20000, 'population' => 40],
                'effects' => ['sensor_range' => 50, 'pirate_detection' => true],
                'operating_costs' => ['credits' => 25, 'quantium' => 0, 'food' => 0, 'minerals' => 0],
                'income' => 0,
            ],
            'super_trading_post' => [
                'name' => '🏬 Super Orbital Trading Post',
                'description' => 'Enhanced trading hub with massive bonuses',
                'required_stage' => 9,
                'base_cost' => ['credits' => 200000, 'minerals' => 50000, 'population' => 100],
                'effects' => ['trade_bonus' => 0.25],
                'operating_costs' => ['credits' => 50, 'quantium' => 0, 'food' => 0, 'minerals' => 0],
                'income' => 300,
            ],
            'shipyard' => [
                'name' => '🚀 Shipyard',
                'description' => 'Constructs new ships for your fleet',
                'required_stage' => 10,
                'base_cost' => ['credits' => 150000, 'minerals' => 40000, 'population' => 150],
                'effects' => ['ship_production_speed' => 20],
                'operating_costs' => ['credits' => 100, 'quantium' => 0, 'food' => 0, 'minerals' => 20],
                'income' => 0,
            ],
            'dockyard' => [
                'name' => '🔧 Dockyard',
                'description' => 'Upgrades and repairs ships',
                'required_stage' => 10,
                'base_cost' => ['credits' => 120000, 'minerals' => 35000, 'population' => 120],
                'effects' => ['repair_speed' => 50, 'upgrade_discount' => 0.15],
                'operating_costs' => ['credits' => 75, 'quantium' => 0, 'food' => 0, 'minerals' => 15],
                'income' => 0,
            ],
        ];
    }

    /**
     * Get building type display name
     */
    public function getTypeDisplay(): string
    {
        $definitions = self::getBuildingDefinitions();

        return $definitions[$this->building_type]['name'] ?? ucfirst(str_replace('_', ' ', $this->building_type));
    }

    /**
     * Get status display
     */
    public function getStatusDisplay(): string
    {
        return match ($this->status) {
            'constructing' => '🏗️ Under Construction',
            'operational' => '✅ Operational',
            'damaged' => '⚠️ Damaged',
            'destroyed' => '❌ Destroyed',
            default => $this->status,
        };
    }

    /**
     * Check if building type can be built at colony's current stage
     */
    public static function canBuildAtStage(string $buildingType, int $colonyStage): bool
    {
        $definitions = self::getBuildingDefinitions();

        return isset($definitions[$buildingType]) && $definitions[$buildingType]['required_stage'] <= $colonyStage;
    }

    /**
     * Get building costs based on type and level
     */
    public static function getBuildingCosts(string $buildingType, int $level = 1): array
    {
        $definitions = self::getBuildingDefinitions();

        if (! isset($definitions[$buildingType])) {
            return ['credits' => 10000, 'minerals' => 5000, 'population' => 20];
        }

        $baseCosts = $definitions[$buildingType]['base_cost'];

        // Scale costs by level (50% increase per level)
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
        $definitions = self::getBuildingDefinitions();

        if (! isset($definitions[$buildingType])) {
            return [];
        }

        $baseEffects = $definitions[$buildingType]['effects'];

        // Scale effects by level (30% increase per level)
        $multiplier = 1 + (($level - 1) * 0.3);

        $scaledEffects = [];
        foreach ($baseEffects as $key => $value) {
            if (is_numeric($value)) {
                $scaledEffects[$key] = (int) ($value * $multiplier);
            } else {
                $scaledEffects[$key] = $value; // Boolean flags don't scale
            }
        }

        return $scaledEffects;
    }

    /**
     * Get operating costs for a building
     */
    public static function getOperatingCosts(string $buildingType, int $level = 1): array
    {
        $definitions = self::getBuildingDefinitions();

        if (! isset($definitions[$buildingType])) {
            return ['credits' => 0, 'quantium' => 0, 'food' => 0, 'minerals' => 0];
        }

        $baseCosts = $definitions[$buildingType]['operating_costs'];

        // Operating costs scale with level (20% increase per level)
        $multiplier = 1 + (($level - 1) * 0.2);

        return [
            'credits' => (int) ($baseCosts['credits'] * $multiplier),
            'quantium' => (int) ($baseCosts['quantium'] * $multiplier),
            'food' => (int) ($baseCosts['food'] * $multiplier),
            'minerals' => (int) ($baseCosts['minerals'] * $multiplier),
        ];
    }

    /**
     * Get income generated by building
     */
    public static function getIncomeGenerated(string $buildingType, int $level = 1): int
    {
        $definitions = self::getBuildingDefinitions();

        if (! isset($definitions[$buildingType]) || $definitions[$buildingType]['income'] === 0) {
            return 0;
        }

        $baseIncome = $definitions[$buildingType]['income'];

        // Income scales with level (50% increase per level)
        $multiplier = 1 + (($level - 1) * 0.5);

        return (int) ($baseIncome * $multiplier);
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

            // Set operating costs and income when building becomes operational
            $operatingCosts = self::getOperatingCosts($this->building_type, $this->level);
            $this->credits_per_cycle = $operatingCosts['credits'];
            $this->quantium_per_cycle = $operatingCosts['quantium'];
            $this->food_per_cycle = $operatingCosts['food'];
            $this->minerals_per_cycle = $operatingCosts['minerals'];

            $this->credits_generated_per_cycle = self::getIncomeGenerated($this->building_type, $this->level);
        }

        $this->save();
    }

    /**
     * Process one cycle (deduct operating costs, generate income)
     */
    public function processCycle(Colony $colony): array
    {
        $log = [];

        if ($this->status !== 'operational') {
            return $log;
        }

        // Check if colony has enough resources for operating costs
        $canOperate = true;

        if ($this->quantium_per_cycle > 0 && $colony->quantium_storage < $this->quantium_per_cycle) {
            $canOperate = false;
            $this->status = 'damaged'; // Shut down due to lack of fuel
            $log[] = "{$this->getTypeDisplay()} shut down - insufficient Quantium";
        }

        if ($canOperate) {
            // Deduct operating costs
            $colony->quantium_storage -= $this->quantium_per_cycle;
            $colony->food_storage -= $this->food_per_cycle;
            $colony->mineral_storage -= $this->minerals_per_cycle;

            // Generate income
            if ($this->credits_generated_per_cycle > 0) {
                $colony->credits_per_cycle += $this->credits_generated_per_cycle;
                $log[] = "{$this->getTypeDisplay()} generated {$this->credits_generated_per_cycle} credits";
            }
        }

        $this->last_cycle_at = now();
        $this->save();

        return $log;
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
        $player->deductCredits($costs['credits']);

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
