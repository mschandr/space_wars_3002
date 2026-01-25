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

    /**
     * Register model event handlers for WarpGate.
     *
     * On creation, ensures the model has a UUID and, if both POI IDs are present while
     * canonical source coordinates are not set, populates canonical coordinates from the POIs.
     */
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
     * Populate this gate's source_x/source_y and dest_x/dest_y with canonical coordinates derived from its source and destination POIs.
     *
     * If the model has loaded sourcePoi/destinationPoi relations those are used; otherwise the POIs are loaded by ID. If both POIs exist their integer X/Y values are ordered canonically (smaller X, then smaller Y) and assigned to the source_* and dest_* attributes. If either POI is missing, no attributes are modified.
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

    /**
     * Get the galaxy this warp gate belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The related Galaxy model.
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function sourcePoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'source_poi_id');
    }

    /**
     * Get the destination PointOfInterest associated with this warp gate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo Relation to the destination PointOfInterest using `destination_poi_id`.
     */
    public function destinationPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'destination_poi_id');
    }

    /**
     * Get the source point of interest associated with this warp gate.
     *
     * Alias of sourcePoi maintained for backward compatibility.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The related PointOfInterest model representing the source POI.
     */
    public function fromPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'source_poi_id');
    }

    /**
     * Alias for the destination point-of-interest relationship for backward compatibility.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo The relation to the destination PointOfInterest model.
     */
    public function toPoi(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'destination_poi_id');
    }

    /**
     * Defines the one-to-one relationship to a WarpLanePirate associated with this warp gate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne The HasOne relation for WarpLanePirate keyed by `warp_gate_id`.
     */
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
     * Determine whether the given ship can detect this warp gate.
     *
     * Detection rules:
     * - Visible gates are always detectable.
     * - Mirror gates require the configured mirror sensor level.
     * - Other hidden gates require the configured base sensor requirement adjusted by scanner bonus.
     *
     * @param PlayerShip $ship The player's ship whose sensor level is evaluated.
     * @return bool `true` if the ship's sensors meet the gate's detection requirement, `false` otherwise.
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
     * Determine whether the gate is in the dormant state.
     *
     * @return bool `true` if status equals 'dormant', `false` otherwise.
     */
    public function isDormant(): bool
    {
        return $this->status === 'dormant';
    }

    /**
     * Determine whether the warp gate's status is active.
     *
     * @return bool `true` if the gate's status is `'active'`, `false` otherwise.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
         * Determine whether the warp gate's status is `destroyed`.
         *
         * @return bool `true` if the gate's status equals 'destroyed', `false` otherwise.
         */
    public function isDestroyed(): bool
    {
        return $this->status === 'destroyed';
    }

    /**
     * Determine whether the given player meets this gate's activation requirements.
     *
     * Returns false if the gate is not dormant; otherwise evaluates the gate's
     * activation_requirements (supported types: `sensor_level`, `credits`, `item`)
     * against the player's current state.
     *
     * @param Player $player The player attempting to activate the gate.
     * @return bool `true` if the player can activate the gate, `false` otherwise.
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
         * Determine whether the given player possesses a plan with the specified name.
         *
         * @param Player $player The player whose plans will be checked.
         * @param string $itemName The name of the required item (plan) to look for.
         * @return bool `true` if the player has a plan named `$itemName`, `false` otherwise.
         */
    private function playerHasItem(Player $player, string $itemName): bool
    {
        // Check player's plans for the item
        return $player->plans()->where('name', $itemName)->exists();
    }

    /**
     * Set the gate to active and record the activation time if it is currently dormant.
     *
     * Updates the model's `status` to `'active'`, sets `activated_at` to the current time, and persists the change.
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
     * Record that the given player discovered this warp gate.
     *
     * Adds the player's id to the gate's `discovered_by` array and persists the model only if the id was not already present.
     *
     * @param Player $player The player who discovered the gate.
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
         * Determine whether the given player has discovered this gate.
         *
         * @param Player $player The player to check.
         * @return bool `true` if the player's id is recorded in `discovered_by`, `false` otherwise.
         */
    public function isDiscoveredBy(Player $player): bool
    {
        return in_array($player->id, $this->discovered_by ?? []);
    }

    /**
     * Return the human-readable description of the gate's activation requirements.
     *
     * @return string|null The `description` field from `activation_requirements` if present, or `null` when no requirements or no description is set.
     */
    public function getActivationDescription(): ?string
    {
        if (empty($this->activation_requirements)) {
            return null;
        }

        return $this->activation_requirements['description'] ?? null;
    }

    /**
         * Filter the query to only include gates with status 'dormant'.
         *
         * @param \Illuminate\Database\Eloquent\Builder $query The query builder.
         * @return \Illuminate\Database\Eloquent\Builder The query builder constrained to gates with status 'dormant'.
         */
    public function scopeDormant($query)
    {
        return $query->where('status', 'dormant');
    }

    /**
         * Modify the query to include only gates with status "active".
         *
         * @param \Illuminate\Database\Eloquent\Builder $query The query builder instance to modify.
         * @return \Illuminate\Database\Eloquent\Builder The query builder filtered to active gates.
         */
    public function scopeActiveGates($query)
    {
        return $query->where('status', 'active');
    }
}