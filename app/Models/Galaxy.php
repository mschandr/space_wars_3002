<?php

namespace App\Models;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Faker\Common\GalaxySuffixes;
use App\Faker\Common\RomanNumerals;
use App\Faker\Providers\GalaxyNameProvider;
use App\Traits\HasUuidAndVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Galaxy extends Model
{
    use HasFactory, HasUuidAndVersion;

    protected $fillable = [
        'galaxy_uuid', 'name', 'description', 'width', 'height', 'seed', 'distribution_method',
        'spacing_factor', 'engine', 'turn_limit', 'status', 'version', 'is_public', 'config',
    ];

    protected $casts = [
        'status' => GalaxyStatus::class,
        'distribution_method' => GalaxyDistributionMethod::class,
        'engine' => GalaxyRandomEngine::class,
        'config' => 'array',
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
}
