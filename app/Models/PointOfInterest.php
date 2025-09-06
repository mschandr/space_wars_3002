<?php

namespace App\Models;

use App\Faker\Providers\NebulaNameProvider;
use App\Faker\Providers\PlanetNameProvider;
use App\Faker\Providers\StarNameProvider;
use Assert\AssertionFailedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use mschandr\WeightedRandom\WeightedRandomGenerator;

class PointOfInterest extends Model
{
    protected $fillable = [
        'uuid',
        'galaxy_id',
        'type',
        'status',
        'x',
        'y',
        'name',
        'attributes',
        'is_hidden',
    ];

    protected $casts = [
        'attributes'    => 'array',
        'is_hidden'     => 'boolean',
        'status'        => PointOfInterestStatus::class,
        'type'          => PointOfInterestType::class,
    ];

    /**
     * Bulk create POIs for a galaxy from a list of points.
     *
     * @param Galaxy $galaxy
     * @param array $points
     * @throws AssertionFailedException
     */
    public static function createForGalaxy(Galaxy $galaxy, array $points): void
    {
        $typeChooser = new WeightedRandomGenerator();
        $typeChooser->registerValues([
            PointOfInterestType::STAR->value            => 60,
            PointOfInterestType::NEBULA->value          => 10,
            PointOfInterestType::ROGUE_PLANET->value    => 10,
            PointOfInterestType::BLACK_HOLE->value      => 5,
            PointOfInterestType::ASTEROID_BELT->value   => 10,
            PointOfInterestType::ANOMALY->value         => 5,
        ]);

        $hiddenChooser = new WeightedRandomGenerator();
        $hiddenChooser->registerValues([
            true    => 10,
            false   => 90,
        ]);

        foreach ($points as $point) {
            $type       = $typeChooser->generate();
            $isHidden   = $hiddenChooser->generate();

            // Generate name based on type
            $name = match ($type) {
                PointOfInterestType::STAR->value            => StarNameProvider::starName(),
                PointOfInterestType::NEBULA->value          => NebulaNameProvider::nebulaName(),
                PointOfInterestType::ROGUE_PLANET->value    => PlanetNameProvider::planetName(),
                default                                     => null,
            };

            self::create([
                'galaxy_id'         => $galaxy->id,
                'uuid'              => (string)Str::uuid(),
                'type'              => $type,
                'status'            => PointOfInterestStatus::DRAFT,
                'x'                 => $point['x'],
                'y'                 => $point['y'],
                'name'              => $name,
                'attributes'        => [],
                'is_hidden'         => $isHidden,
            ]);
        }
    }

   /**
    *--------------------------------------------------------------------------
    * Relationships
    *--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string)Str::uuid();
            }
        });
    }

   /**
    *--------------------------------------------------------------------------
    * Helpers
    *--------------------------------------------------------------------------
    */

    public function galaxy()
    {
        return $this->belongsTo(Galaxy::class);
    }
}
