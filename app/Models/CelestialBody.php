<?php

namespace App\Models;

use App\Enums\BodyType;
use Illuminate\Database\Eloquent\{Concerns\HasUuids, Factories\HasFactory, Model, Relations\BelongsTo};
use Illuminate\Support\Str;
use mschandr\WeightedRandom\WeightedRandomGenerator;


class CelestialBody extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'celestial_body_type_id',   // Foreign key referencing CelestialBodyType
        'name',                     // Optional because this can be named by the user at a later date.
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = (string)Str::uuid();
        });
    }

    /**
     *  Return relationships here
     */
    public function CelestialBodyType(): BelongsTo
    {
        return $this->belongsTo(CelestialBodyTypes::class);
    }

    /**
     * @returns string
     */
    public function getRandomWeightedCelestialBodyTypeId(): string
    {
        $celestial_bodies = CelestialBodyTypes::whereIn('name', BodyType::UniverseBodyTypes)
            ->get(['id', 'name']);
        $generator = new WeightedRandomGenerator();
        foreach ($celestial_bodies as $key => $celestial_body_type) {
            $generator->registerValue($celestial_body_type->id, $celestial_body_type->getWeight());
        }
        return $generator->generate();
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function checkForNameCollision(string $name): bool
    {
        return CelestialBody::where('name', '=', $name)
            ->exists();
    }

}
