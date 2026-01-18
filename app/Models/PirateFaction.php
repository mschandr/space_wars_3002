<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PirateFaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'name',
        'description',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_active'  => 'boolean',
    ];

    // Relationships
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function captains(): HasMany
    {
        return $this->hasMany(PirateCaptain::class, 'faction_id');
    }

    public function fleets(): HasManyThrough
    {
        return $this->hasManyThrough(PirateFleet::class, PirateCaptain::class, 'faction_id', 'captain_id');
    }

    // Helpers
    public function getFullName(): string
    {
        return "The {$this->name}";
    }
}
