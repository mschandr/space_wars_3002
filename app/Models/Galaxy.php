<?php

namespace App\Models;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxySizeTier;
use App\Enums\Galaxy\GalaxyStatus;
use App\Faker\Common\GalaxySuffixes;
use App\Faker\Common\RomanNumerals;
use App\Faker\Providers\GalaxyNameProvider;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Galaxy extends Model
{
    use HasFactory, HasUuidAndVersion;

    protected $fillable = [
        'galaxy_uuid', 'name', 'description', 'width', 'height', 'seed', 'distribution_method',
        'spacing_factor', 'engine', 'turn_limit', 'status', 'version', 'is_public', 'config',
        'game_mode', 'owner_user_id', 'size_tier', 'core_bounds', 'progress_status',
        'generation_started_at', 'generation_completed_at',
    ];

    protected $casts = [
        'status' => GalaxyStatus::class,
        'distribution_method' => GalaxyDistributionMethod::class,
        'engine' => GalaxyRandomEngine::class,
        'size_tier' => GalaxySizeTier::class,
        'config' => 'array',
        'core_bounds' => 'array',
        'progress_status' => 'array',
        'generation_started_at' => 'datetime',
        'generation_completed_at' => 'datetime',
    ];

    /**
     * Create a Galaxy model instance from provided attributes.
     *
     * Accepts an associative array with required keys `width`, `height`, `seed`, `distribution_method`, and `engine`.
     * Optional keys: `turn_limit` (defaults to 0), `description` (defaults to null), and `is_public` (defaults to false).
     * When the `game_config.feature.persist_data` configuration is truthy, the created Galaxy is persisted to storage; otherwise a filled but unsaved instance is returned.
     *
     * @param array $galaxyData Associative array of galaxy attributes.
     * @return self The created Galaxy instance; persisted when data persistence is enabled, unsaved otherwise.
     */
    public static function createGalaxy(array $galaxyData): self
    {
        $attributes = [
            'width' => $galaxyData['width'],
            'height' => $galaxyData['height'],
            'seed' => $galaxyData['seed'],
            'distribution_method' => $galaxyData['distribution_method'],
            'engine' => $galaxyData['engine'],
            'status' => GalaxyStatus::DRAFT,
            'turn_limit' => $galaxyData['turn_limit'] ?? 0,
            'description' => $galaxyData['description'] ?? null,
            'is_public' => $galaxyData['is_public'] ?? false,
        ];

        if (config('game_config.feature.persist_data')) {
            return self::create($attributes);
        }
        $galaxy = new self;
        $galaxy->fill($attributes);

        return $galaxy;
    }

    /**
     * @static
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($galaxy) {
            if (empty($galaxy->name)) {
                $galaxy->name = self::generateUniqueName();
            }
        });
    }

    /**
     * @static
     */
    public static function generateUniqueName(): string
    {
        $base = GalaxyNameProvider::generateGalaxyName();
        $name = $base;

        if (self::where('name', $name)->exists()) {
            foreach (GalaxySuffixes::$suffixes as $suffix) {
                $candidate = $base.$suffix;
                if (! self::where('name', $candidate)->exists()) {
                    return $candidate;
                }
            }

            $i = 2;
            do {
                $candidate = $base.' '.RomanNumerals::romanize($i);
                $i++;
            } while (self::where('name', $candidate)->exists());

            return $candidate;
        }

        return $name;
    }

    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class);
    }

    public function warpGates(): HasMany
    {
        return $this->hasMany(WarpGate::class);
    }

    public function sectors(): HasMany
    {
        return $this->hasMany(Sector::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * Get the trading hubs associated with the galaxy through its points of interest.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough A has-many-through relation for TradingHub models accessed via PointOfInterest.
     */
    public function tradingHubs(): HasManyThrough
    {
        return $this->hasManyThrough(TradingHub::class, PointOfInterest::class, 'galaxy_id', 'poi_id');
    }

    /**
     * Get the NPCs associated with the galaxy.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany Relation for `Npc` models belonging to this galaxy.
     */
    public function npcs(): HasMany
    {
        return $this->hasMany(Npc::class);
    }

    /**
     * Determine whether the galaxy uses single-player game mode.
     *
     * @return bool `true` if the galaxy's `game_mode` equals 'single_player', `false` otherwise.
     */
    public function isSinglePlayer(): bool
    {
        return $this->game_mode === 'single_player';
    }

    /**
     * Determine whether the galaxy uses the multiplayer game mode.
     *
     * @return bool `true` if `game_mode` is 'multiplayer', `false` otherwise.
     */
    public function isMultiplayer(): bool
    {
        return $this->game_mode === 'multiplayer';
    }

    /**
     * Determines whether NPCs are permitted in this galaxy.
     *
     * @return bool `true` if the galaxy's `game_mode` is 'single_player' or 'mixed', `false` otherwise.
     */
    public function allowsNpcs(): bool
    {
        return in_array($this->game_mode, ['single_player', 'mixed']);
    }

    /**
     * Get paired galaxy (works both ways - prime<->mirror)
     */
    public function getPairedGalaxy(): ?Galaxy
    {
        if ($this->isMirrorUniverse()) {
            // Mirror galaxy stores prime_galaxy_id
            $primeId = $this->config['prime_galaxy_id'] ?? null;

            return $primeId ? Galaxy::find($primeId) : null;
        } else {
            // Prime galaxy stores mirror_galaxy_id
            $mirrorId = $this->config['mirror_galaxy_id'] ?? null;

            return $mirrorId ? Galaxy::find($mirrorId) : null;
        }
    }

    /**
     * Check if this is a mirror universe galaxy
     */
    public function isMirrorUniverse(): bool
    {
        return ($this->config['is_mirror'] ?? false) === true;
    }

    /**
     * Apply mirror multiplier to a value
     *
     * @param  float  $baseValue  The base value to multiply
     * @param  string  $type  Type of multiplier: resource, price, pirate_difficulty, rare_spawn
     */
    public function applyMirrorMultiplier(float $baseValue, string $type): float
    {
        if (! $this->isMirrorUniverse()) {
            return $baseValue;
        }

        $modifiers = $this->getMirrorModifiers();
        $multiplier = match ($type) {
            'resource' => $modifiers['resource_multiplier'] ?? 1.0,
            'price' => $modifiers['price_boost'] ?? 1.0,
            'pirate_difficulty' => $modifiers['pirate_difficulty_boost'] ?? 1.0,
            'rare_spawn' => $modifiers['rare_mineral_spawn_rate'] ?? 1.0,
            default => 1.0,
        };

        return $baseValue * $multiplier;
    }

    /**
         * Retrieve mirror-universe modifier values for this galaxy.
         *
         * When the galaxy is configured as a mirror universe, returns an associative array mapping modifier
         * names to their numeric values; returns an empty array when the galaxy is not a mirror universe.
         *
         * @return array{
         *     resource_multiplier?: float,
         *     price_boost?: float,
         *     pirate_difficulty_boost?: float,
         *     rare_mineral_spawn_rate?: float
         * } Associative array of modifier keys to numeric values, or an empty array if not applicable.
         */
    public function getMirrorModifiers(): array
    {
        if (! $this->isMirrorUniverse()) {
            return [];
        }

        return $this->config['mirror_modifiers'] ?? [
            'resource_multiplier' => 2.0,
            'price_boost' => 1.5,
            'pirate_difficulty_boost' => 2.0,
            'rare_mineral_spawn_rate' => 3.0,
        ];
    }

    /**
     * Indicates whether the galaxy has an assigned size tier.
     *
     * @return bool `true` if `size_tier` is set, `false` otherwise.
     */
    public function isTieredGalaxy(): bool
    {
        return $this->size_tier !== null;
    }

    /**
     * Determines whether the given coordinates lie inside the galaxy's core bounds.
     *
     * If `core_bounds` is not set, the method returns `false`.
     *
     * @param float $x X coordinate.
     * @param float $y Y coordinate.
     * @return bool `true` if the coordinate is within `core_bounds` inclusive (`x_min`..`x_max`, `y_min`..`y_max`), `false` otherwise.
     */
    public function isInCoreRegion(float $x, float $y): bool
    {
        if (! $this->core_bounds) {
            return false;
        }

        return $x >= $this->core_bounds['x_min']
            && $x <= $this->core_bounds['x_max']
            && $y >= $this->core_bounds['y_min']
            && $y <= $this->core_bounds['y_max'];
    }

    /**
     * Record or update a generation progress step for the galaxy and persist it.
     *
     * @param int $step Numeric identifier for the progress step.
     * @param string $name Human-readable name for the step.
     * @param int $percentage Completion percentage for the step (0-100).
     * @param string $status Status label for the step (e.g., 'running', 'completed', 'failed').
     * @param string|null $message Optional additional information about the step.
     */
    public function updateProgress(int $step, string $name, int $percentage, string $status = 'running', ?string $message = null): void
    {
        $progress = $this->progress_status ?? [];

        $progress[$step] = [
            'step' => $step,
            'name' => $name,
            'percentage' => $percentage,
            'status' => $status,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->progress_status = $progress;
        $this->save();
    }

    /**
     * Retrieve the latest generation progress percentage.
     *
     * Returns the `percentage` value from the most recent progress step, or 0 when no progress is recorded.
     *
     * @return int Latest progress percentage (0–100), or 0 if no progress exists.
     */
    public function getCurrentProgress(): int
    {
        if (empty($this->progress_status)) {
            return 0;
        }

        $latestStep = collect($this->progress_status)->sortByDesc('step')->first();

        return $latestStep['percentage'] ?? 0;
    }

    /**
         * Retrieve related PointOfInterest models that are in the core region.
         *
         * @return HasMany A query for PointOfInterest records filtered to region = 'core'.
         */
    public function corePointsOfInterest(): HasMany
    {
        return $this->pointsOfInterest()->where('region', 'core');
    }

    /**
     * Retrieve points of interest that belong to the outer region.
     *
     * @return HasMany A relation scoped to PointOfInterest records where `region` is `"outer"`.
     */
    public function outerPointsOfInterest(): HasMany
    {
        return $this->pointsOfInterest()->where('region', 'outer');
    }
}