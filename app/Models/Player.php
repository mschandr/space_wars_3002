<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'current_poi_id',
        'status',
    ];

    protected $casts = [
        'credits' => 'decimal:2',
        'experience' => 'integer',
        'level' => 'integer',
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

    public function ships(): HasMany
    {
        return $this->hasMany(PlayerShip::class);
    }

    public function activeShip()
    {
        return $this->hasOne(PlayerShip::class)->where('is_active', true);
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

    public function hasChartFor(PointOfInterest $poi): bool
    {
        return $this->starCharts()->where('revealed_poi_id', $poi->id)->exists();
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
        if (!$this->meetsRequirements($plan->requirements)) {
            return [
                'success' => false,
                'message' => 'Requirements not met',
            ];
        }

        // Deduct credits
        if (!$this->deductCredits($plan->price)) {
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
        if (!$this->isInMirrorUniverse()) {
            return false;
        }

        if (!$this->mirror_universe_entry_time) {
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
        if (!$this->isInMirrorUniverse() || !$this->mirror_universe_entry_time) {
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
