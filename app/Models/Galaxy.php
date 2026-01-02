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
}
