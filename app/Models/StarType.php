<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @method static insert(array[] $star_types)
 */
class StarType extends Model
{
    use HasUuids;

    const STAR_MAGNETIC_LEVEL_VERY_STRONG   = 1;
    const STAR_MAGNETIC_LEVEL_STRONG        = 2;
    const STAR_MAGNETIC_LEVEL_MODERATE      = 3;
    const STAR_MAGNETIC_LEVEL_WEAK          = 4;
    const STAR_MAGNETIC_LEVEL_VERY_WEAK     = 5;

    protected $table = 'star_type';
    public $incrementing = false;   // Set the primary key type to string
    protected $keyType = 'string';  // Disable auto-incrementing
    public $primaryKey = 'id';
    protected $fillable = [
        'id',                // Uuid
        'classification',    // enum
        'name',
        'age_min',
        'age_max',
        'temperature_min',
        'temperature_max',
        'magnetic_field',   // strong, moderate, weak, very strong and very weak
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

}
