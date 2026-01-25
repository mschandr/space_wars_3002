<?php

namespace App\Models;

use App\Enums\WarpGate\GateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class WarpGate extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'source_poi_id',
        'destination_poi_id',
        'source_x',
        'source_y',
        'dest_x',
        'dest_y',
        'is_hidden',
        'distance',
        'status',
        'gate_type',
        'activation_requirements',
        'discovered_by',
        'activated_at',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'distance' => 'float',
        'gate_type' => GateType::class,
        'activation_requirements' => 'array',
        'discovered_by' => 'array',
        'activated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gate) {
            if (empty($gate->uuid)) {
                $gate->uuid = Str::uuid();
            }

            // Auto-populate canonical coordinates if POIs are set
            if ($gate->source_poi_id && $gate->destination_poi_id && empty($gate->source_x)) {
                $gate->populateCanonicalCoordinates();
            }
        });
    }

    /**
     * Get canonical coordinate ordering for a gate pair.
     * Lower X comes first; if X equal, lower Y comes first.
     *
     * @param  int  $x1  First point X
     * @param  int  $y1  First point Y
     * @param  int  $x2  Second point X
     * @param  int  $y2  Second point Y
     * @return array{source_x: int, source_y: int, dest_x: int, dest_y: int}
     */
    public static function canonicalCoordinates(int $x1, int $y1, int $x2, int $y2): array
    {
        // Sort by X first, then by Y if X is equal
        if ($x1 < $x2 || ($x1 === $x2 && $y1 <= $y2)) {
            return [
                'source_x' => $x1,
                'source_y' => $y1,
                'dest_x' => $x2,
                'dest_y' => $y2,
            ];
        }

        return [
            'source_x' => $x2,
            'source_y' => $y2,
            'dest_x' => $x1,
            'dest_y' => $y1,
        ];
    }

    /**
     * Populate canonical coordinates from source/destination POIs.
     */
    public function populateCanonicalCoordinates(): void
    {
        $source = $this->sourcePoi ?? PointOfInterest::find($this->source_poi_id);
        $dest = $this->destinationPoi ?? PointOfInterest::find($this->destination_poi_id);

        if ($source && $dest) {
            $coords = self::canonicalCoordinates(
                (int) $source->x,
                (int) $source->y,
                (int) $dest->x,
                (int) $dest->y
            );

            $this->source_x = $coords['source_x'];
            $this->source_y = $coords['source_y'];
            $this->dest_x = $coords['dest_x'];
            $this->dest_y = $coords['dest_y'];
        }
    }

    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function sourcePoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'source_poi_id');
    }

    public function destinationPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'destination_poi_id');
    }

    /**
     * Alias for sourcePoi (for backward compatibility)
     */
    public function fromPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'source_poi_id');
    }

    /**
     * Alias for destinationPoi (for backward compatibility)
     */
    public function toPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'destination_poi_id');
    }

    public function warpLanePirate(): HasOne
    {
        return $this->hasOne(WarpLanePirate::class, 'warp_gate_id');
    }

    public function calculateDistance(): float
    {
        $source = $this->sourcePoi;
        $destination = $this->destinationPoi;

        if (! $source || ! $destination) {
            return 0.0;
        }

        $dx = $destination->x - $source->x;
        $dy = $destination->y - $source->y;

        return sqrt($dx * $dx + $dy * $dy);
    }

    public function isAccessible(): bool
    {
        return ! $this->is_hidden && $this->status === 'active';
    }

    /**
     * Check if this is a mirror universe gate
     */
    public function isMirrorGate(): bool
    {
        if (is_string($this->gate_type)) {
            return in_array($this->gate_type, ['mirror_entry', 'mirror_return']);
        }

        return $this->gate_type instanceof GateType && $this->gate_type->isMirrorGate();
    }

    /**
     * Check if player can detect this gate based on their sensor level
     */
    public function canPlayerDetect(PlayerShip $ship): bool
    {
        // Visible gates are always detectable
        if (! $this->is_hidden) {
            return true;
        }

        // Mirror gates require high sensor level
        if ($this->isMirrorGate()) {
            $requiredSensorLevel = config('game_config.mirror_universe.required_sensor_level', 5);

            return $ship->sensors >= $requiredSensorLevel;
        }

        // Regular hidden gates use existing logic
        $scannerBonus = config('game_config.gates.scanner_bonus', 0.2);
        $baseSensorRequirement = config('game_config.gates.base_sensor_requirement', 2);

        return $ship->sensors >= ($baseSensorRequirement - $scannerBonus);
    }

    /**
     * Check if this gate is dormant.
     */
    public function isDormant(): bool
    {
        return $this->status === 'dormant';
    }

    /**
     * Check if this gate is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if this gate is destroyed.
     */
    public function isDestroyed(): bool
    {
        return $this->status === 'destroyed';
    }

    /**
     * Check if a player can activate this dormant gate.
     */
    public function canBeActivatedBy(Player $player): bool
    {
        if (! $this->isDormant()) {
            return false;
        }

        if (empty($this->activation_requirements)) {
            return true;  // No requirements, can activate
        }

        $requirements = $this->activation_requirements;
        $ship = $player->activeShip;

        return match ($requirements['type'] ?? null) {
            'sensor_level' => $ship && $ship->sensors >= ($requirements['value'] ?? 1),
            'credits' => $player->credits >= ($requirements['value'] ?? 0),
            'item' => $this->playerHasItem($player, $requirements['value'] ?? ''),
            default => true,
        };
    }

    /**
     * Check if player has a required item.
     */
    private function playerHasItem(Player $player, string $itemName): bool
    {
        // Check player's plans for the item
        return $player->plans()->where('name', $itemName)->exists();
    }

    /**
     * Activate this dormant gate.
     */
    public function activate(): void
    {
        if (! $this->isDormant()) {
            return;
        }

        $this->status = 'active';
        $this->activated_at = now();
        $this->save();
    }

    /**
     * Mark this gate as discovered by a player.
     */
    public function markDiscoveredBy(Player $player): void
    {
        $discovered = $this->discovered_by ?? [];

        if (! in_array($player->id, $discovered)) {
            $discovered[] = $player->id;
            $this->discovered_by = $discovered;
            $this->save();
        }
    }

    /**
     * Check if a player has discovered this gate.
     */
    public function isDiscoveredBy(Player $player): bool
    {
        return in_array($player->id, $this->discovered_by ?? []);
    }

    /**
     * Get the activation requirement description.
     */
    public function getActivationDescription(): ?string
    {
        if (empty($this->activation_requirements)) {
            return null;
        }

        return $this->activation_requirements['description'] ?? null;
    }

    /**
     * Query scope for dormant gates.
     */
    public function scopeDormant($query)
    {
        return $query->where('status', 'dormant');
    }

    /**
     * Query scope for active gates.
     */
    public function scopeActiveGates($query)
    {
        return $query->where('status', 'active');
    }
}
