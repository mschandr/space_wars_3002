<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Npc extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'current_poi_id',
        'last_trading_hub_poi_id',
        'call_sign',
        'archetype',
        'credits',
        'experience',
        'level',
        'ships_destroyed',
        'combats_won',
        'combats_lost',
        'total_trade_volume',
        'difficulty',
        'aggression',
        'risk_tolerance',
        'trade_focus',
        'personality',
        'status',
        'current_activity',
        'last_action_at',
        'last_mirror_travel_at',
    ];

    protected $casts = [
        'credits' => 'decimal:2',
        'experience' => 'integer',
        'level' => 'integer',
        'ships_destroyed' => 'integer',
        'combats_won' => 'integer',
        'combats_lost' => 'integer',
        'total_trade_volume' => 'decimal:2',
        'aggression' => 'float',
        'risk_tolerance' => 'float',
        'trade_focus' => 'float',
        'personality' => 'array',
        'last_action_at' => 'datetime',
        'last_mirror_travel_at' => 'datetime',
    ];

    /**
     * NPC Archetypes with their default configurations
     */
    public const ARCHETYPES = [
        'trader' => [
            'aggression' => 0.1,
            'risk_tolerance' => 0.3,
            'trade_focus' => 0.9,
            'description' => 'Focus on profitable trade routes',
        ],
        'explorer' => [
            'aggression' => 0.2,
            'risk_tolerance' => 0.7,
            'trade_focus' => 0.4,
            'description' => 'Seek out uncharted systems',
        ],
        'pirate_hunter' => [
            'aggression' => 0.8,
            'risk_tolerance' => 0.6,
            'trade_focus' => 0.2,
            'description' => 'Hunt down pirates for bounties',
        ],
        'miner' => [
            'aggression' => 0.1,
            'risk_tolerance' => 0.4,
            'trade_focus' => 0.6,
            'description' => 'Extract and sell minerals',
        ],
        'merchant' => [
            'aggression' => 0.05,
            'risk_tolerance' => 0.2,
            'trade_focus' => 0.95,
            'description' => 'Wealthy trade magnate',
        ],
    ];

    /**
     * Difficulty multipliers for NPC stats/behavior
     */
    public const DIFFICULTY_MULTIPLIERS = [
        'easy' => [
            'credits' => 0.5,
            'combat_skill' => 0.6,
            'decision_quality' => 0.5,
        ],
        'medium' => [
            'credits' => 1.0,
            'combat_skill' => 1.0,
            'decision_quality' => 0.75,
        ],
        'hard' => [
            'credits' => 1.5,
            'combat_skill' => 1.3,
            'decision_quality' => 0.9,
        ],
        'expert' => [
            'credits' => 2.0,
            'combat_skill' => 1.6,
            'decision_quality' => 1.0,
        ],
    ];

    /**
     * Register model boot hooks to ensure an NPC has a UUID before creation.
     *
     * Registers a creating event listener that assigns a new UUID to the model's
     * `uuid` attribute if it is empty.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($npc) {
            if (empty($npc->uuid)) {
                $npc->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the galaxy that the NPC belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The relation to the Galaxy model.
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    / **
     * Get the PointOfInterest that represents the NPC's current location.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo BelongsTo relation to the PointOfInterest model for the NPC's current_poi_id.
     */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'current_poi_id');
    }

    /**
     * Get the NPC's current point of interest relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The BelongsTo relationship for the NPC's current PointOfInterest.
     */
    public function currentPoi(): BelongsTo
    {
        return $this->currentLocation();
    }

    /**
     * Get the NPC's ships relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Relation for the NPC's NpcShip models.
     */
    public function ships(): HasMany
    {
        return $this->hasMany(NpcShip::class);
    }

    /**
     * Get the NPC's currently active ship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne Relation for the NPC's active App\Models\NpcShip (filtered by `is_active = true`).
     */
    public function activeShip(): HasOne
    {
        return $this->hasOne(NpcShip::class)->where('is_active', true);
    }

    /**
     * Increase the NPC's credits balance by the specified amount and persist the change.
     *
     * @param float $amount The amount of credits to add to the NPC's balance.
     */
    public function addCredits(float $amount): void
    {
        $this->credits += $amount;
        $this->save();
    }

    /**
     * Deducts a specified amount from the NPC's credits and saves the model.
     *
     * @param float $amount The amount of credits to deduct.
     * @return bool `true` if the amount was deducted and persisted, `false` if the NPC has insufficient credits.
     */
    public function deductCredits(float $amount): bool
    {
        if ($this->credits < $amount) {
            return false;
        }

        $this->credits -= $amount;
        $this->save();

        return true;
    }

    /**
     * Increases the NPC's experience by the given amount, updates level if thresholds are crossed, and persists the model.
     *
     * @param int $amount The number of experience points to add.
     */
    public function addExperience(int $amount): void
    {
        $this->experience += $amount;

        $newLevel = $this->calculateLevel($this->experience);
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
        }

        $this->save();
    }

    /**
     * Determine the NPC's level from total experience points.
     *
     * @param int $experience Total accumulated experience points.
     * @return int NPC level (1-based), computed as floor(sqrt($experience / 100)) + 1.
     */
    protected function calculateLevel(int $experience): int
    {
        return (int) floor(sqrt($experience / 100)) + 1;
    }

    /**
     * Determine whether the NPC's current location is in the mirror universe.
     *
     * @return bool `true` if the NPC's current location's galaxy is a mirror universe, `false` otherwise.
     */
    public function isInMirrorUniverse(): bool
    {
        $currentGalaxy = $this->currentLocation?->galaxy;

        return $currentGalaxy ? $currentGalaxy->isMirrorUniverse() : false;
    }

    /**
         * Retrieve the multiplier for a given stat according to this NPC's current difficulty.
         *
         * @param string $stat The stat key to lookup (e.g., 'credits', 'combat_skill', 'decision_quality').
         * @return float The multiplier for the specified stat based on the NPC's difficulty; `1.0` if not defined.
         */
    public function getDifficultyMultiplier(string $stat): float
    {
        return self::DIFFICULTY_MULTIPLIERS[$this->difficulty][$stat] ?? 1.0;
    }

    /**
     * Retrieve the configuration for the NPC's archetype.
     *
     * Falls back to the `'trader'` archetype if the NPC's archetype is not defined in ARCHETYPES.
     *
     * @return array The archetype configuration array (e.g., `aggression`, `risk_tolerance`, `trade_focus`, `description`).
     */
    public function getArchetypeConfig(): array
    {
        return self::ARCHETYPES[$this->archetype] ?? self::ARCHETYPES['trader'];
    }

    /**
         * Set the NPC's current activity, update the last action timestamp, and persist the change.
         *
         * @param string $activity The activity identifier or description to assign to the NPC.
         */
    public function setActivity(string $activity): void
    {
        $this->current_activity = $activity;
        $this->last_action_at = now();
        $this->save();
    }
}