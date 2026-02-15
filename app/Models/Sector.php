<?php

namespace App\Models;

use App\Enums\PointsOfInterest\PointOfInterestType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'name',
        'grid_x',
        'grid_y',
        'x_min',
        'x_max',
        'y_min',
        'y_max',
        'attributes',
        'danger_level',
    ];

    protected $casts = [
        'attributes' => 'array',
        'grid_x' => 'integer',
        'grid_y' => 'integer',
        'x_min' => 'float',
        'x_max' => 'float',
        'y_min' => 'float',
        'y_max' => 'float',
        'danger_level' => 'integer',
    ];

    /**
     * Get the galaxy that owns this sector
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Check if coordinates are within this sector
     */
    public function containsCoordinates(float $x, float $y): bool
    {
        return $x >= $this->x_min && $x <= $this->x_max &&
            $y >= $this->y_min && $y <= $this->y_max;
    }

    /**
     * Get danger level (based on pirate presence)
     */
    public function getDangerLevel(): string
    {
        $stats = $this->getStats();
        $pirateRatio = $stats['star_count'] > 0
            ? $stats['pirate_count'] / $stats['star_count']
            : 0;

        if ($pirateRatio >= 0.3) {
            return 'high';
        }
        if ($pirateRatio >= 0.1) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get sector statistics
     */
    public function getStats(): array
    {
        $stars = $this->pointsOfInterest()
            ->where('type', PointOfInterestType::STAR)
            ->count();

        $totalPOIs = $this->pointsOfInterest()->count();

        // Count pirates in this sector
        $pirates = WarpLanePirate::whereHas('warpGate', function ($query) {
            $query->whereHas('sourcePoi', function ($q) {
                $q->where('sector_id', $this->id);
            });
        })->where('is_active', true)->count();

        return [
            'star_count' => $stars,
            'poi_count' => $totalPOIs,
            'pirate_count' => $pirates,
        ];
    }

    /**
     * Get all POIs in this sector
     */
    public function pointsOfInterest(): HasMany
    {
        return $this->hasMany(PointOfInterest::class);
    }

    /**
     * Get display name with grid coordinates
     */
    public function getDisplayName(): string
    {
        return "{$this->name} [{$this->grid_x},{$this->grid_y}]";
    }
}
