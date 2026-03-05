<?php

namespace App\Models;

use App\Enums\Crew\CrewAlignment;
use App\Enums\Crew\CrewRole;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewMember extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'name',
        'role',
        'alignment',
        'player_ship_id',
        'current_poi_id',
        'shady_actions',
        'reputation',
        'traits',
        'backstory',
    ];

    protected $casts = [
        'role' => CrewRole::class,
        'alignment' => CrewAlignment::class,
        'shady_actions' => 'integer',
        'reputation' => 'integer',
        'traits' => 'array',
    ];

    /**
     * Get the galaxy this crew member is associated with
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the ship this crew member is assigned to (null if available for hire)
     */
    public function playerShip(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class)->withDefault();
    }

    /**
     * Get the POI where this crew member can be found
     */
    public function currentPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class);
    }

    /**
     * Record a shady action for this crew member
     */
    public function recordShadyAction(): void
    {
        $this->increment('shady_actions');
    }

    /**
     * Record a reputation change
     */
    public function addReputation(int $amount): void
    {
        $this->increment('reputation', $amount);
    }

    /**
     * Get a specific trait value (0.0-1.0)
     */
    public function getTrait(string $traitName): float
    {
        $traits = $this->traits ?? [];
        return $traits[$traitName] ?? 0.0;
    }
}
