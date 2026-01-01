<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PirateFaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'uuid',
        'name',
        'description',
        'attributes',
        'is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function captains(): HasMany
    {
        return $this->hasMany(PirateCaptain::class, 'faction_id');
    }

    // Helpers
    public function getFullName(): string
    {
        return "The {$this->name}";
    }
}
