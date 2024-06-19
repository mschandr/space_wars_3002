<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StarType extends Model
{
    use HasFactory, HasUuids;

    const STAR_MAGNETIC_LEVEL_STRONG    = 1;
    const STAR_MAGNETIC_LEVEL_MODERATE  = 2;
    const STAR_MAGNETIC_LEVEL_WEAK      = 3;

    protected $table = 'star_system';
    public $incrementing = false;   // Set the primary key type to string
    protected $keyType = 'string';  // Disable auto-incrementing
    public $primaryKey = 'id';
    protected $fillable = [
        'id',                // Uuid
        'classification',    // enum
        'name',              
        'age',
        'temperature',
        'magnetic_field'    // strong, moderate, weak,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }
}
