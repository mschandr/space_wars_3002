<?php

namespace App\Models;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Enums\Galaxy\GalaxyStatus;
use App\Faker\SpaceProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Galaxy extends Model
{
    /** @use HasFactory<\Database\Factories\GalaxyFactory> */
    use HasFactory;

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

    public static function boot()
    {
        parent::boot();

        static::creating(function ($galaxy) {
            if (empty($galaxy->galaxy_uuid)) {
                $galaxy->galaxy_uuid = (string) Str::uuid();
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

    public static function GenerateGalaxyName(): string
    {
        return SpaceProvider::generateGalaxyName();
    }
}
