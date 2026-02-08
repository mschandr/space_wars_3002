<?php

namespace App\Models;

use App\Enums\Exploration\ScanLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * System Scan Model
 *
 * Represents a player's scan data for a specific point of interest (system).
 * Scan data is cached and updated when the player re-scans with higher sensor levels.
 */
class SystemScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'player_id',
        'poi_id',
        'scan_level',
        'scan_data',
        'scanned_at',
    ];

    protected $casts = [
        'scan_level' => 'integer',
        'scan_data' => 'array',
        'scanned_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($scan) {
            if (empty($scan->uuid)) {
                $scan->uuid = Str::uuid();
            }
            if (empty($scan->scanned_at)) {
                $scan->scanned_at = now();
            }
        });
    }

    /**
     * The player who performed this scan.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * The point of interest (system) that was scanned.
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Get the ScanLevel enum for this scan.
     */
    public function getScanLevelEnum(): ScanLevel
    {
        return ScanLevel::fromSensorLevel($this->scan_level);
    }

    /**
     * Check if this scan can reveal a specific feature type.
     */
    public function canReveal(string $featureType): bool
    {
        return $this->getScanLevelEnum()->canReveal($featureType);
    }

    /**
     * Get scan data for a specific level.
     *
     * @param  int  $level  The scan level (1-9)
     * @return array|null The scan data for that level, or null if not scanned at that level
     */
    public function getDataForLevel(int $level): ?array
    {
        $data = $this->scan_data ?? [];

        return $data[(string) $level] ?? null;
    }

    /**
     * Get all scan data up to and including the current scan level.
     *
     * @return array Combined scan data from all achieved levels
     */
    public function getAllData(): array
    {
        $combined = [];
        $data = $this->scan_data ?? [];

        for ($level = 1; $level <= $this->scan_level; $level++) {
            if (isset($data[(string) $level])) {
                $combined = array_merge_recursive($combined, $data[(string) $level]);
            }
        }

        return $combined;
    }

    /**
     * Check if this scan needs updating (player has higher sensors than recorded scan level).
     */
    public function needsUpdate(int $sensorLevel): bool
    {
        return $sensorLevel > $this->scan_level;
    }

    /**
     * Get the UI color for this scan level.
     */
    public function getColor(): string
    {
        return $this->getScanLevelEnum()->color();
    }

    /**
     * Get the UI opacity for this scan level.
     */
    public function getOpacity(): float
    {
        return $this->getScanLevelEnum()->opacity();
    }

    /**
     * Get what the next scan level would reveal.
     *
     * @return array|null Categories revealed at next level, or null if at max
     */
    public function getNextLevelReveals(): ?array
    {
        $next = $this->getScanLevelEnum()->next();

        return $next?->reveals();
    }

    /**
     * Check if there are more levels to unlock.
     */
    public function canRevealMore(): bool
    {
        return $this->scan_level < ScanLevel::max()->value;
    }

    /**
     * Update the scan with new data from a higher sensor level.
     *
     * @param  int  $newLevel  The new scan level achieved
     * @param  array  $newData  The scan data to merge in
     */
    public function updateScanData(int $newLevel, array $newData): void
    {
        if ($newLevel <= $this->scan_level) {
            return;
        }

        $currentData = $this->scan_data ?? [];
        $currentData[(string) $newLevel] = $newData;

        $this->scan_level = $newLevel;
        $this->scan_data = $currentData;
        $this->scanned_at = now();
        $this->save();
    }
}
