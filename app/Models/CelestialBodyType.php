<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CelestialBodyType extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'celestial_body_type';
    protected $keyType = 'string'; // Set the primary key type to string
    public $incrementing = false; // Disable auto-incrementing
    public $primaryKey = 'id';
    protected $fillable = [
        'id',               // uuid
        'name',             // Name of the celestial object (e.g. Star, Planet, Black Hole, Comet, Neutron whatever)
        'description',      // Optional description of the body of the type
        'universe_weight',  // Weight of the universal item
        'system_weight',    // Weight of the system objects
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    public function getWeight(): int
    {
        return ($this->universe_weight!==0) ? $this->universe_weight : $this->system_weight;
    }

    // Define relationships here (optional for future features)
    public function celestialBodies(): HasMany
    {
        return $this->hasMany(CelestialBody::class);
    }

    public function getStarId(): string
    {
        return CelestialBodyType::where('name', 'Star')->first()->id;
    }

    public function getNebulaId(): string
    {
        return CelestialBodyType::where('name', 'Nebula')->first()->id;
    }

    public function getMoonId(): string
    {
        return CelestialBodyType::where('name', 'Moon')->first()->id;
    }

    public function getAsteriodBeltId(): string
    {
        return CelestialBodyType::where('name', 'Asteroid Belt')->first()->id;
    }

    public function getBlackHoleId(): string
    {
        return CelestialBodyType::where('name', 'Black Hole')->first()->id;
    }

    public function getPlanetId(): string
    {
        return CelestialBodyType::where('name', 'Planet')->first()->id;
    }

    public function getCometId(): string
    {
        return CelestialBodyType::where('name', 'Comet')->first()->id;
    }

    public function getAsteroidId(): string
    {
        return CelestialBodyType::where('name', 'Asteroid')->first()->id;
    }

    public function getDwarfPlanetId(): string
    {
        return CelestialBodyType::where('name', 'Dwarf Planet')->first()->id;
    }
}
