<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Mobile pirate bands that roam within their sector.
 *
 * Replaces static WarpLanePirate with dynamic, sector-based encounters.
 * Each band has a home base (uninhabited system) and roams nearby systems.
 */
class PirateBand extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'sector_id',
        'home_base_poi_id',
        'captain_id',
        'fleet_size',
        'difficulty_tier',
        'is_active',
        'current_poi_id',
        'last_moved_at',
        'last_encounter_at',
        'roaming_radius_ly',
    ];

    protected $casts = [
        'fleet_size' => 'integer',
        'difficulty_tier' => 'integer',
        'is_active' => 'boolean',
        'last_moved_at' => 'datetime',
        'last_encounter_at' => 'datetime',
        'roaming_radius_ly' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($band) {
            if (empty($band->uuid)) {
                $band->uuid = Str::uuid();
            }
        });
    }

    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function homeBase(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'home_base_poi_id');
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(PirateCaptain::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'current_poi_id');
    }

    /**
     * Record that an encounter happened.
     */
    public function recordEncounter(): void
    {
        $this->last_encounter_at = now();
        $this->save();
    }

    /**
     * Move this pirate band to a new location within roaming radius.
     */
    public function relocate(PointOfInterest $newLocation): void
    {
        $this->current_poi_id = $newLocation->id;
        $this->last_moved_at = now();
        $this->save();
    }

    /**
     * Scope to filter active pirate bands.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by sector.
     */
    public function scopeInSector($query, int $sectorId)
    {
        return $query->where('sector_id', $sectorId);
    }

    /**
     * Scope to filter by galaxy.
     */
    public function scopeInGalaxy($query, int $galaxyId)
    {
        return $query->where('galaxy_id', $galaxyId);
    }
}
