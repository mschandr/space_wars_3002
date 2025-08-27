<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StarSystem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'star_system_uuid',         // uuid -> to prevent url hacking
        'celestial_body_id',        // Uuid -> external foreign key
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
