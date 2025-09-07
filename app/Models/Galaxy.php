<?php

namespace App\Models;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Faker\Common\GalaxySuffixes;
use App\Faker\Common\RomanNumerals;
use App\Faker\Providers\GalaxyNameProvider;
use Assert\AssertionFailedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Galaxy extends Model
{
    protected $fillable = [
        'galaxy_uuid',
        'name',
        'description',
        'width',
        'height',
        'seed',
        'distribution_method',
        'spacing_factor',
        'engine',
        'turn_limit',
        'status',
        'version',
        'is_public',
        'config',
    ];

    protected $casts = [
        'status'              => GalaxyStatus::class,
        'distribution_method' => GalaxyDistributionMethod::class,
        'engine'              => GalaxyRandomEngine::class,
        'config'              => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($galaxy) {
            if (empty($galaxy->galaxy_uuid)) {
                $galaxy->galaxy_uuid = (string) Str::uuid();
            }
            if (empty($galaxy->version)) {
                $galaxy->version = trim(file_get_contents(base_path('VERSION')));
            }
            if (empty($galaxy->name)) {
                $galaxy->name = self::generateUniqueName();
            }
        });
    }

    /**
     * @return HasMany
     */
    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class);
    }

    /**
     * @param array $galaxyData
     * @return self
     */
    public static function createGalaxy(array $galaxyData): self
    {
        return self::create([
            'width'               => $galaxyData['width'],
            'height'              => $galaxyData['height'],
            'seed'                => $galaxyData['seed'],
            'distribution_method' => $galaxyData['distribution_method'],
            'engine'              => $galaxyData['engine'],
            'status'              => GalaxyStatus::DRAFT,
            'turn_limit'          => $galaxyData['turn_limit'] ?? 0,
            'description'         => $galaxyData['description'] ?? null,
            'is_public'           => $galaxyData['is_public'] ?? false,
        ]);
    }

    /**
     * @throws AssertionFailedException
     */
    public static function createWithPoints(array $galaxyData, array $points): self
    {
        $galaxy = self::createGalaxy($galaxyData);
        PointOfInterest::createPointsForGalaxy($galaxy, $points);
        return $galaxy;
    }

    public static function generateUniqueName(): string
    {
        $base = GalaxyNameProvider::generateGalaxyName();
        $name = $base;

        if (self::where('name', $name)->exists()) {
            foreach (GalaxySuffixes::$suffixes as $suffix) {
                $candidate = $base . ' ' . $suffix;
                if (!self::where('name', $candidate)->exists()) {
                    return $candidate;
                }
            }

            $i = 2;
            do {
                $candidate = $base . ' ' . RomanNumerals::romanize($i);
                $i++;
            } while (self::where('name', $candidate)->exists());

            return $candidate;
        }

        return $name;
    }
}
