<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'galaxy_id',
        'call_sign',
        'credits',
        'experience',
        'level',
        'ships_destroyed',
        'combats_won',
        'combats_lost',
        'total_trade_volume',
        'current_poi_id',
        'last_trading_hub_poi_id',
        'last_mirror_travel_at',
        'status',
    ];

    protected $casts = [
        'credits' => 'decimal:2',
        'experience' => 'integer',
        'level' => 'integer',
        'ships_destroyed' => 'integer',
        'combats_won' => 'integer',
        'combats_lost' => 'integer',
        'total_trade_volume' => 'decimal:2',
        'last_mirror_travel_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($player) {
            if (empty($player->uuid)) {
                $player->uuid = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'current_poi_id');
    }

    // Alias for currentLocation
    public function currentPoi(): BelongsTo
    {
        return $this->currentLocation();
    }

    public function ships(): HasMany
    {
        return $this->hasMany(PlayerShip::class);
    }

    public function activeShip()
    {
        return $this->hasOne(PlayerShip::class)->where('is_active', true);
    }

    public function colonies(): HasMany
    {
        return $this->hasMany(Colony::class);
    }

    public function combatParticipations(): HasMany
    {
        return $this->hasMany(CombatParticipant::class);
    }

    public function cargos(): HasMany
    {
        // Get cargo from all player ships (primarily the active ship)
        return $this->hasManyThrough(
            PlayerCargo::class,
            PlayerShip::class,
            'player_id',      // Foreign key on player_ships table
            'player_ship_id', // Foreign key on player_cargos table
            'id',             // Local key on players table
            'id'              // Local key on player_ships table
        );
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'player_plans')
            ->withTimestamps()
            ->withPivot('acquired_at');
    }

    public function starCharts(): BelongsToMany
    {
        return $this->belongsToMany(PointOfInterest::class, 'player_star_charts', 'player_id', 'revealed_poi_id')
            ->withPivot('purchased_from_poi_id', 'price_paid', 'purchased_at')
            ->withTimestamps();
    }

    /**
     * Request-scoped cache for charted POI IDs
     * Prevents N+1 queries when checking multiple systems
     */
    protected ?array $chartedPoiIdsCache = null;

    /**
     * Get all charted POI IDs for this player (cached for request lifecycle)
     * Use this for bulk checking instead of individual hasChartFor() calls
     *
     * @return array<int> Array of POI IDs the player has charts for
     */
    public function getChartedPoiIds(): array
    {
        if ($this->chartedPoiIdsCache === null) {
            $this->chartedPoiIdsCache = $this->starCharts()
                ->pluck('revealed_poi_id')
                ->toArray();
        }

        return $this->chartedPoiIdsCache;
    }

    /**
     * Check if player has chart for a POI by ID (in-memory lookup)
     * More efficient than hasChartFor() when checking multiple systems
     *
     * @param  int  $poiId  The POI ID to check
     * @return bool True if player has chart for this POI
     */
    public function hasChartForId(int $poiId): bool
    {
        return in_array($poiId, $this->getChartedPoiIds(), true);
    }

    /**
     * Clear the charted POI cache (call after purchasing new charts)
     */
    public function clearChartedPoiCache(): void
    {
        $this->chartedPoiIdsCache = null;
    }

    public function hasChartFor(PointOfInterest $poi): bool
    {
        // Use optimized in-memory lookup
        return $this->hasChartForId($poi->id);
    }

    public function addCredits(float $amount): void
    {
        $this->credits += $amount;
        $this->save();
    }

    public function deductCredits(float $amount): bool
    {
        if ($this->credits < $amount) {
            return false;
        }

        $this->credits -= $amount;
        $this->save();

        return true;
    }

    public function addExperience(int $amount): void
    {
        $this->experience += $amount;

        $newLevel = $this->calculateLevel($this->experience);
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
        }

        $this->save();
    }

    protected function calculateLevel(int $experience): int
    {
        return (int) floor(sqrt($experience / 100)) + 1;
    }

    /**
     * Get total additional levels for a component from owned plans
     */
    public function getAdditionalLevelsForComponent(string $component): int
    {
        return $this->plans()
            ->where('component', $component)
            ->sum('additional_levels');
    }

    /**
     * Get count of specific plan owned
     */
    public function getPlanCount(int $planId): int
    {
        return $this->plans()
            ->where('plans.id', $planId)
            ->count();
    }

    /**
     * Purchase a plan
     */
    public function purchasePlan(Plan $plan): array
    {
        // Check credits
        if ($this->credits < $plan->price) {
            return [
                'success' => false,
                'message' => 'Insufficient credits',
            ];
        }

        // Check requirements (if any)
        if (! $this->meetsRequirements($plan->requirements)) {
            return [
                'success' => false,
                'message' => 'Requirements not met',
            ];
        }

        // Deduct credits
        if (! $this->deductCredits($plan->price)) {
            return [
                'success' => false,
                'message' => 'Transaction failed',
            ];
        }

        // Add plan (allows duplicates)
        $this->plans()->attach($plan->id, [
            'acquired_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Successfully purchased {$plan->getFullName()}",
            'plan' => $plan,
        ];
    }

    /**
     * Check if player meets plan requirements
     */
    private function meetsRequirements(?array $requirements): bool
    {
        if (empty($requirements)) {
            return true;
        }

        // Check level requirement
        if (isset($requirements['min_level']) && $this->level < $requirements['min_level']) {
            return false;
        }

        // Check prerequisite plans
        if (isset($requirements['prerequisite_plans'])) {
            foreach ($requirements['prerequisite_plans'] as $requiredPlanId) {
                if ($this->getPlanCount($requiredPlanId) === 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if player is currently in the mirror universe
     */
    public function isInMirrorUniverse(): bool
    {
        $currentGalaxy = $this->currentLocation?->galaxy;

        return $currentGalaxy ? $currentGalaxy->isMirrorUniverse() : false;
    }

    /**
     * Check if player can return from mirror universe (cooldown check)
     */
    public function canReturnFromMirror(): bool
    {
        if (! $this->isInMirrorUniverse()) {
            return false;
        }

        if (! $this->mirror_universe_entry_time) {
            return true; // No entry time recorded, allow return
        }

        $cooldownHours = config('game_config.mirror_universe.return_cooldown_hours', 24);
        $canReturnAt = Carbon::parse($this->mirror_universe_entry_time)->addHours($cooldownHours);

        return now()->greaterThanOrEqualTo($canReturnAt);
    }

    /**
     * Get time remaining until can return from mirror universe
     */
    public function getMirrorCooldownRemaining(): ?Carbon
    {
        if (! $this->isInMirrorUniverse() || ! $this->mirror_universe_entry_time) {
            return null;
        }

        $cooldownHours = config('game_config.mirror_universe.return_cooldown_hours', 24);
        $canReturnAt = Carbon::parse($this->mirror_universe_entry_time)->addHours($cooldownHours);

        return $canReturnAt->isFuture() ? $canReturnAt : null;
    }

    /**
     * Record mirror universe entry
     */
    public function enterMirrorUniverse(): void
    {
        $this->mirror_universe_entry_time = now();
        $this->save();
    }

    /**
     * Clear mirror universe entry time
     */
    public function exitMirrorUniverse(): void
    {
        $this->mirror_universe_entry_time = null;
        $this->save();
    }
}
