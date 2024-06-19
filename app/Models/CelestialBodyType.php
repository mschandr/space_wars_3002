<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Enums\BodyType;
use Mockery\Exception;

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
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    // #[NoReturn]
    public function getWeight(): int
    {
        $weight_type = BodyType::whatBodyTypeIs($this->name);
        $path_string = "game_config.".strtolower($weight_type)."_weights.".strtolower(str_replace(' ', '_', $this->name))."_weight";
        if  (config($path_string)===null) {
            throw new Exception("path string = ".$path_string." doesn't exist");
        }
        return config($path_string);
    }

    // Define relationships here (optional for future features)
    public function celestialBodies(): HasMany
    {
        return $this->hasMany(CelestialBody::class);
    }

    public function getId(BodyType $bodyType): string
    {
        return CelestialBodyType::where('name', $bodyType->value)->first()->id;
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

    public function getSuperMassiveBlackHoleId(): string
    {
        return CelestialBodyType::where('name', 'Super Massive Black Hole')->first()->id;
    }
}
