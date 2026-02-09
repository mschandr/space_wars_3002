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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Galaxy extends Model
{
    use HasFactory, HasUuidAndVersion;

    protected $fillable = [
        'galaxy_uuid', 'name', 'description', 'width', 'height', 'seed', 'distribution_method',
        'spacing_factor', 'engine', 'turn_limit', 'status', 'version', 'is_public', 'config',
        'game_mode', 'max_players', 'owner_user_id', 'size_tier', 'sector', 'core_bounds', 'progress_status',
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

    public function tradingHubs(): HasManyThrough
    {
        return $this->hasManyThrough(TradingHub::class, PointOfInterest::class, 'galaxy_id', 'poi_id');
    }

    public function npcs(): HasMany
    {
        return $this->hasMany(Npc::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Check if this is a single-player galaxy
     */
    public function isSinglePlayer(): bool
    {
        return $this->game_mode === 'single_player';
    }

    /**
     * Check if this is a multiplayer galaxy
     */
    public function isMultiplayer(): bool
    {
        return $this->game_mode === 'multiplayer';
    }

    /**
     * Check if this galaxy allows NPCs
     * All galaxies now support NPCs.
     */
    public function allowsNpcs(): bool
    {
        return true;
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
     * Scope to exclude mirror universe galaxies from queries.
     * Mirror universes should not appear in galaxy listings - they are accessed via gates.
     */
    public function scopeExcludeMirrors($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('config')
                ->orWhereRaw("JSON_EXTRACT(config, '$.is_mirror') IS NULL")
                ->orWhereRaw("JSON_EXTRACT(config, '$.is_mirror') = false");
        });
    }

    /**
     * Scope to only include mirror universe galaxies.
     */
    public function scopeOnlyMirrors($query)
    {
        return $query->whereRaw("JSON_EXTRACT(config, '$.is_mirror') = true");
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
     * Get mirror universe modifiers for this galaxy
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
     * Check if this is a tiered galaxy.
     */
    public function isTieredGalaxy(): bool
    {
        return $this->size_tier !== null;
    }

    /**
     * Check if coordinates are within the core region.
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
     * Update progress status for galaxy generation.
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
     * Get the current generation progress percentage.
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
     * Get core region POIs.
     */
    public function corePointsOfInterest(): HasMany
    {
        return $this->pointsOfInterest()->where('region', 'core');
    }

    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class);
    }

    /**
     * Get outer region POIs.
     */
    public function outerPointsOfInterest(): HasMany
    {
        return $this->pointsOfInterest()->where('region', 'outer');
    }
}
