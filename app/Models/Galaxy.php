<?php

namespace App\Models;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Faker\Common\GalaxySuffixes;
use App\Faker\Common\RomanNumerals;
use App\Faker\Providers\GalaxyNameProvider;
use Illuminate\Database\Eloquent\Model;
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
        'status' => GalaxyStatus::class,
        'distribution_method' => GalaxyDistributionMethod::class,
        'engine' => GalaxyRandomEngine::class,
        'config' => 'array',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($galaxy) {
            if (empty($galaxy->galaxy_uuid)) {
                $galaxy->galaxy_uuid = (string)Str::uuid();
            }
        });
    }

    public function pointsOfInterest()
    {
        return $this->hasMany(PointOfInterest::class);
    }

    public function getGalaxyName(): string
    {
        return $this->name;
    }

    public function createWithPoints(array $galaxyData, array $points): self
    {
        $galaxy = self::create([
            'uuid'                      => $galaxyData['uuid'],
            'name'                      => self::generateUniqueName(),
            'width'                     => $galaxyData['width'],
            'height'                    => $galaxyData['height'],
            'seed'                      => $galaxyData['seed'],
            'distribution_method'       => GalaxyDistributionMethod::$galaxyData['distribution_method'],
            'engine'                    => $galaxyData['engine'],
            'status'                    => $galaxyData ['status'] ?? GalaxyStatus::DRAFT,
            'turn_limit'                => $galaxyData['turn_limit'] ?? 0,
            'version'                   => $galaxyData['version'] ?? '1.0',
            'description'               => $galaxyData['description'] ?? null,
            'is_public'                 => $galaxyData['is_public'] ?? false,
        ]);

        PointOfInterest::createForGalaxy($galaxy, $points);
        return $galaxy;
    }

    public static function generateUniqueName(): string
    {
        $base = GalaxyNameProvider::generateGalaxyName();
        $name = $base;

        if (Galaxy::where('name', $name)->exists()) {
            // Try suffixes first
            foreach (GalaxySuffixes::$suffixes as $suffix) {
                $candidate = $base . ' ' . $suffix;
                if (!Galaxy::where('name', $candidate)->exists()) {
                    return $candidate;
                }
            }

            // Fallback: add numerals
            $i = 2;
            do {
                $candidate = $base . ' ' . RomanNumerals::romanize($i);
                $i++;
            } while (Galaxy::where('name', $candidate)->exists());

            return $candidate;
        }

        return $name;
    }

}
