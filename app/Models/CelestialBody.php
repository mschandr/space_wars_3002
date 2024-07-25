<?php

namespace App\Models;

use App\Enums\BodyType;
use Illuminate\Database\Eloquent\{Concerns\HasUuids, Factories\HasFactory, Model, Relations\BelongsTo};
use Illuminate\Support\Str;
use mschandr\WeightedRandom\WeightedRandomGenerator;


class CelestialBody extends Model
{
    use HasFactory, HasUuids;

public $incrementing = false;
        public $primaryKey = 'id';   // Set the primary key type to string
        protected $table = 'celestial_body';  // Disable auto-incrementing
protected $keyType = 'string';
    protected $fillable = [
        'id',                       // Uuid
        'celestial_body_type_id',   // Foreign key referencing CelestialBodyType
        'name',                     // Optional because this can be named by the user at a later date.
        'x_coordinate',
        'y_coordinate',
        ''
        // More to come at a later date and time maybe quantity of resources remaining, this will have to be held in
        // cache somewhere otherwise can you imagine what's going to happen?
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    /**
     *  Return relationships here
     */
    public function CelestialBodyType(): BelongsTo
    {
        return $this->belongsTo(CelestialBodyType::class);
    }

    /**
     * @returns string
     */
    public function getRandomWeightedCelestialBodyTypeId(): string
    {
        $celestial_bodies = CelestialBodyType::whereIn('name', BodyType::UniverseBodyTypes)
                                             ->get(['id', 'name']);
        $generator        = new WeightedRandomGenerator();
        foreach ($celestial_bodies as $key => $celestial_body_type) {
            $generator->registerValue($celestial_body_type->id, $celestial_body_type->getWeight());
        }
        return $generator->generate();
    }

    /**
     * @param  string  $name
     *
     * @return bool
     */
    public function checkForNameCollision(string $name): bool
    {
        return CelestialBody::where('name', '=', $name)
                            ->exists();
    }

    /**
     * @param  int  $x_coordinate
     * @param  int  $y_coordinate
     *
     * @return bool
     */
    public function checkForCoordinatesCollision(int $x_coordinate, int $y_coordinate): bool
    {
        return CelestialBody::where('x_coordinate', $x_coordinate)
                            ->where('y_coordinate', $y_coordinate)
                            ->exists();
    }
}
