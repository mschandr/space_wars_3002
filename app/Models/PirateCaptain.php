<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PirateCaptain extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'faction_id',
        'first_name',
        'last_name',
        'title',
        'combat_skill',
        'attributes',
    ];

    protected $casts = [
        'combat_skill' => 'integer',
        'attributes' => 'array',
    ];

    // Relationships
    public function faction(): BelongsTo
    {
        return $this->belongsTo(PirateFaction::class);
    }

    public function fleet(): HasMany
    {
        return $this->hasMany(PirateFleet::class, 'captain_id');
    }

    public function activeFleet(): HasMany
    {
        return $this->fleet()->where('status', 'active');
    }

    // Helpers
    public function getFullName(): string
    {
        return "{$this->title} {$this->first_name} {$this->last_name}";
    }

    public function getFullTitle(): string
    {
        return "{$this->getFullName()} of {$this->faction->getFullName()}";
    }
}
