<?php

namespace App\Models;

use App\Enums\BodyType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CelestialBody extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'celestial_body';
    public $incrementing = false;   // Set the primary key type to string
    protected $keyType = 'string';  // Disable auto-incrementing
    public $primaryKey = 'id';
    protected $fillable = [
        'id',                       // Uuid
        'celestial_body_type_id',   // Foreign key referencing CelestialBodyType
        'name',                     // Optional because this can be named by the user at a later date.
        'x_coordinate',
        'y_coordinate',
        ''
        // More to come at a later date and time maybe quantity of resources remaining, (this will have to be held in
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

    public function getObjectsByCelestialBodyTypeId(BodyType $bodyType): Collection
    {
        return Collect(['ob', 'sga']);
    }
}
