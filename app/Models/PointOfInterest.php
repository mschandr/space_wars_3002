<?php

namespace App\Models;

use App\Faker\Providers\NebulaNameProvider;
use App\Faker\Providers\PlanetNameProvider;
use App\Faker\Providers\StarNameProvider;
use Assert\AssertionFailedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use App\Enums\PointsOfInterest\PointOfInterestStatus;
use App\Enums\PointsOfInterest\PointOfInterestType;
use mschandr\WeightedRandom\WeightedRandomGenerator;

class PointOfInterest extends Model
{
    /**
     * @var string
     */
    protected $table = "points_of_interest";

    protected      $fillable = [
        'uuid',
        'galaxy_id',
        'type',
        'status',
        'x',
        'y',
        'name',
        'attributes',
        'is_hidden',
        'version',
    ];
    protected      $casts    = [
        'attributes' => 'array',
        'is_hidden'  => 'boolean',
        'status'     => PointOfInterestStatus::class,
        'type'       => PointOfInterestType::class,
    ];
    private string $version  = "";

    /**
     * Bulk create POIs for a galaxy from a list of points.
     *
     * @param Galaxy $galaxy
     * @param array $points
     * @throws AssertionFailedException
     */
    public static function createPointsForGalaxy(Galaxy $galaxy, array $points): void
    {
        foreach ($points as $point) {
            $type     = self::setPOIType();
            $isHidden = self::setHiddenPOI();

            // Generate name based on type
            $name = match ($type) {
                PointOfInterestType::STAR->value         => StarNameProvider::starName(),
                PointOfInterestType::NEBULA->value       => NebulaNameProvider::nebulaName(),
                PointOfInterestType::ROGUE_PLANET->value => PlanetNameProvider::planetName(),
                default                                  => null,
            };

            self::create([
                'galaxy_id'  => $galaxy->id,
                'uuid'       => (string)Str::uuid(),
                'type'       => $type,
                'status'     => PointOfInterestStatus::DRAFT,
                'x'          => $point[0],
                'y'          => $point[1],
                'name'       => $name,
                'attributes' => [],
                'is_hidden'  => $isHidden,
            ]);
        }
    }

    /**
     * @return mixed
     * @throws AssertionFailedException
     */
    private static function setPOIType(): string
    {
        $typeChooser = new WeightedRandomGenerator();
        $typeChooser->registerValues([
            PointOfInterestType::STAR->value          => 60,
            PointOfInterestType::NEBULA->value        => 10,
            PointOfInterestType::ROGUE_PLANET->value  => 10,
            PointOfInterestType::BLACK_HOLE->value    => 5,
            PointOfInterestType::ASTEROID_BELT->value => 10,
            PointOfInterestType::ANOMALY->value       => 5,
        ]);
        return $typeChooser->generate();
    }

    /**
     * @return bool
     * @throws AssertionFailedException
     */
    private static function setHiddenPOI(): bool
    {
        $hiddenChooser = new WeightedRandomGenerator();
        $hiddenChooser->registerValues([
            true  => 10,
            false => 90,
        ]);
        return $hiddenChooser->generate();
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
            if (empty($model->version)) {
                $model->version = (string)trim(file_get_contents(base_path('VERSION')));
            }
        });
    }

    /**
     *--------------------------------------------------------------------------
     * Helpers
     *--------------------------------------------------------------------------
     */

    /**
     * @return BelongsTo
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }
}
