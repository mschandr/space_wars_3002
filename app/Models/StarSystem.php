<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StarSystem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'star_system';
    public $incrementing = false;   // Set the primary key type to string
    protected $keyType = 'string';  // Disable auto-incrementing
    public $primaryKey = 'id';
    protected $fillable = [
        'id',                       // Uuid
        'celestial_body_id',        // Uuid -> external foreign key
        'template_id',              // Uuid -> template id for the system
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }
}
