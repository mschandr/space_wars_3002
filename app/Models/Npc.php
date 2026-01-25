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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($npc) {
            if (empty($npc->uuid)) {
                $npc->uuid = Str::uuid();
            }
        });
    }

    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'current_poi_id');
    }

    public function currentPoi(): BelongsTo
    {
        return $this->currentLocation();
    }

    public function ships(): HasMany
    {
        return $this->hasMany(NpcShip::class);
    }

    public function activeShip(): HasOne
    {
        return $this->hasOne(NpcShip::class)->where('is_active', true);
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
     * Check if NPC is currently in the mirror universe
     */
    public function isInMirrorUniverse(): bool
    {
        $currentGalaxy = $this->currentLocation?->galaxy;

        return $currentGalaxy ? $currentGalaxy->isMirrorUniverse() : false;
    }

    /**
     * Get the difficulty multiplier for a specific stat
     */
    public function getDifficultyMultiplier(string $stat): float
    {
        return self::DIFFICULTY_MULTIPLIERS[$this->difficulty][$stat] ?? 1.0;
    }

    /**
     * Get archetype configuration
     */
    public function getArchetypeConfig(): array
    {
        return self::ARCHETYPES[$this->archetype] ?? self::ARCHETYPES['trader'];
    }

    /**
     * Update current activity
     */
    public function setActivity(string $activity): void
    {
        $this->current_activity = $activity;
        $this->last_action_at = now();
        $this->save();
    }
}
