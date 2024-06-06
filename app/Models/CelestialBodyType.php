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
        'description'       // Optional description of the body of the type
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    // Define relationships here (optional for future features)
    public function celestialBodies(): HasMany
    {
        return $this->hasMany(CelestialBody::class);
    }
}
