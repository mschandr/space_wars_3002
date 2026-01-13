<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Colony extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'player_id',
        'poi_id',
        'name',
        'population',
        'population_growth_rate',
        'max_population',
        'food_production',
        'food_storage',
        'mineral_production',
        'mineral_storage',
        'quantium_storage',
        'credits_per_cycle',
        'development_level',
        'defense_rating',
        'garrison_strength',
        'habitability_rating',
        'status',
        'established_at',
        'last_growth_at',
        'last_attacked_at',
    ];

    protected $casts = [
        'population' => 'integer',
        'population_growth_rate' => 'decimal:2',
        'max_population' => 'integer',
        'food_production' => 'integer',
        'food_storage' => 'integer',
        'mineral_production' => 'integer',
        'mineral_storage' => 'integer',
        'quantium_storage' => 'integer',
        'credits_per_cycle' => 'integer',
        'development_level' => 'integer',
        'defense_rating' => 'integer',
        'garrison_strength' => 'integer',
        'habitability_rating' => 'decimal:2',
        'established_at' => 'datetime',
        'last_growth_at' => 'datetime',
        'last_attacked_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($colony) {
            if (empty($colony->uuid)) {
                $colony->uuid = Str::uuid();
            }
            if (empty($colony->established_at)) {
                $colony->established_at = now();
            }
        });
    }

    /**
     * Get the player that owns the colony
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Get the point of interest (planet) this colony is on
     */
    public function poi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Get all buildings in this colony
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(ColonyBuilding::class);
    }

    /**
     * Get ship production queue for this colony
     */
    public function shipProduction(): HasMany
    {
        return $this->hasMany(ColonyShipProduction::class);
    }

    /**
     * Get missions launched from this colony
     */
    public function missions(): HasMany
    {
        return $this->hasMany(ColonyMission::class);
    }

    /**
     * Process population growth
     */
    public function processGrowth(): void
    {
        if ($this->population >= $this->max_population) {
            return; // At capacity
        }

        // Base growth rate modified by habitability and development
        $effectiveGrowthRate = $this->population_growth_rate * $this->habitability_rating;

        // Food affects growth
        $foodModifier = min(1.0, $this->food_production / max(1, $this->population / 10));

        $newPopulation = (int) ceil($this->population * (1 + ($effectiveGrowthRate * $foodModifier)));
        $this->population = min($newPopulation, $this->max_population);
        $this->last_growth_at = now();
        $this->save();
    }

    /**
     * Calculate total production from buildings
     */
    public function calculateProduction(): void
    {
        $this->food_production = $this->buildings()
            ->where('status', 'operational')
            ->where('building_type', 'hydroponics')
            ->sum('effects->food_production');

        $this->mineral_production = $this->buildings()
            ->where('status', 'operational')
            ->where('building_type', 'mining_facility')
            ->sum('effects->mineral_production');

        $this->credits_per_cycle = $this->buildings()
            ->where('status', 'operational')
            ->where('building_type', 'trade_station')
            ->sum('effects->credits_per_cycle');

        $this->save();
    }

    /**
     * Check if colony can support a new building
     */
    public function canBuildBuilding(string $buildingType): bool
    {
        $existingCount = $this->buildings()->where('building_type', $buildingType)->count();

        // Limit buildings based on development level
        $maxBuildings = $this->development_level * 2;

        return $this->buildings()->count() < $maxBuildings;
    }

    /**
     * Check if colony has a shipyard
     */
    public function hasShipyard(): bool
    {
        return $this->buildings()
            ->where('building_type', 'shipyard')
            ->where('status', 'operational')
            ->exists();
    }

    /**
     * Get current ship production queue
     */
    public function getProductionQueue()
    {
        return $this->shipProduction()
            ->whereIn('status', ['queued', 'building'])
            ->orderBy('queue_position')
            ->get();
    }

    /**
     * Advance development level
     */
    public function upgradeDevelopmentLevel(int $creditCost, int $mineralCost): bool
    {
        if ($this->development_level >= 10) {
            return false; // Max level
        }

        // Check if player has resources
        $player = $this->player;
        if ($player->credits < $creditCost) {
            return false;
        }

        // Deduct costs
        $player->credits -= $creditCost;
        $player->save();

        // Upgrade
        $this->development_level++;
        $this->max_population += 1000; // Increase capacity
        $this->save();

        return true;
    }

    /**
     * Get status display string
     */
    public function getStatusDisplay(): string
    {
        return match ($this->status) {
            'establishing' => '🏗️ Establishing',
            'growing' => '📈 Growing',
            'established' => '✅ Established',
            'threatened' => '⚠️ Threatened',
            default => $this->status,
        };
    }

    /**
     * Get age of colony in days
     */
    public function getAgeInDays(): int
    {
        return $this->established_at->diffInDays(now());
    }
}
