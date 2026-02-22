<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Precursor Ship Model
 *
 * The ultimate discovery - a vessel from 500,000 years ago.
 *
 * Capabilities:
 * - 100x defense and weapons of any player ship
 * - 1,000,000 unit pocket dimension cargo
 * - Interstellar jump drive (no gates needed)
 * - Infinite fuel regeneration
 * - Requires Sensor Level 12 to detect within 10 unit radius
 *
 * Hidden in interstellar void, away from stars and systems.
 */
class PrecursorShip extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'x',
        'y',
        'is_discovered',
        'discovered_by_player_id',
        'discovered_at',
        'claimed_by_player_id',
        'claimed_at',
        'hull',
        'max_hull',
        'weapons',
        'sensors',
        'speed',
        'warp_drive',
        'cargo_capacity',
        'current_cargo',
        'fuel',
        'max_fuel',
        'precursor_tech',
        'description',
        'precursor_name',
    ];

    protected $casts = [
        'x' => 'integer',
        'y' => 'integer',
        'is_discovered' => 'boolean',
        'discovered_at' => 'datetime',
        'claimed_at' => 'datetime',
        'hull' => 'integer',
        'max_hull' => 'integer',
        'weapons' => 'integer',
        'sensors' => 'integer',
        'speed' => 'integer',
        'warp_drive' => 'integer',
        'cargo_capacity' => 'integer',
        'current_cargo' => 'integer',
        'fuel' => 'integer',
        'max_fuel' => 'integer',
        'precursor_tech' => 'array',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ship) {
            // Set default Precursor tech capabilities
            if (empty($ship->precursor_tech)) {
                $ship->precursor_tech = [
                    'jump_drive' => true,              // Can jump anywhere without gates
                    'pocket_dimension' => true,        // Cargo exists in pocket dimension
                    'shield_harmonics' => 1000,        // Regenerating shields
                    'quantum_sensors' => 100,          // See through nebulae, detect cloaked ships
                    'matter_replicator' => true,       // Self-repair capabilities
                    'temporal_stasis' => true,         // Ship preserved for 500KY
                    'neural_interface' => true,        // Direct mind-ship connection
                    'stellar_cartography' => 'complete', // Full galaxy map from Precursor era
                ];
            }

            // Set lore description
            if (empty($ship->description)) {
                $ship->description = <<<'LORE'
The Void Strider - Flagship of the Precursor Stellar Engineering Corps.

500,000 years ago, this vessel coordinated the repositioning of entire star systems.
Its crew could move planets like chess pieces, harvesting stellar output with
surgical precision.

The ship's hull bears scars from battles with entities we cannot comprehend.
Its databanks contain star charts of galaxies that no longer exist.

Its jump drive can fold space itself - no gate network required.
Its cargo holds exist in pocket dimensions, bounded only by imagination.
Its weapons could crack moons. Its shields laugh at supernovas.

It has waited here, in the cold dark between stars, for someone worthy.
LORE;
            }
        });
    }

    /**
     * Relationships
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    public function discoveredBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'discovered_by_player_id');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'claimed_by_player_id');
    }

    /**
     * Discovery Methods
     */

    /**
     * Check if a player can detect this ship with their sensors
     *
     * Requires:
     * - Sensor Level 12+
     * - Within 10 unit radius
     */
    public function canBeDetectedBy(Player $player): bool
    {
        $ship = $player->activeShip;

        if (! $ship) {
            return false;
        }

        // Requires godlike sensors
        if ($ship->sensors < 12) {
            return false;
        }

        // Must be within 10 unit radius
        $distance = $this->distanceFrom($player->currentLocation->x, $player->currentLocation->y);

        return $distance <= 10;
    }

    /**
     * Calculate distance from given coordinates
     */
    public function distanceFrom(int $x, int $y): float
    {
        $dx = $this->x - $x;
        $dy = $this->y - $y;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Discover the ship
     */
    public function discover(Player $player): void
    {
        if ($this->is_discovered) {
            return;
        }

        $this->is_discovered = true;
        $this->discovered_by_player_id = $player->id;
        $this->discovered_at = now();
        $this->save();

        // Award massive XP
        $player->addExperience(100000); // 100K XP for finding it!
    }

    /**
     * Claim the ship for a player
     *
     * This converts it to a PlayerShip (or creates special ownership)
     */
    public function claim(Player $player): PlayerShip
    {
        $this->claimed_by_player_id = $player->id;
        $this->claimed_at = now();
        $this->save();

        // Create a special PlayerShip entry
        // This is the Precursor ship - it's unique
        $playerShip = PlayerShip::create([
            'uuid' => Str::uuid(),
            'player_id' => $player->id,
            'ship_id' => 1, // Use a special "Precursor" ship type (create separately)
            'current_poi_id' => $player->current_poi_id,
            'hull' => $this->hull,
            'max_hull' => $this->max_hull,
            'weapons' => $this->weapons,
            'sensors' => $this->sensors,
            'speed' => $this->speed,
            'warp_drive' => $this->warp_drive,
            'cargo_capacity' => $this->cargo_capacity,
            'current_cargo' => $this->current_cargo,
            'current_fuel' => $this->fuel,
            'max_fuel' => $this->max_fuel,
            'is_active' => false, // Player chooses when to activate
        ]);

        return $playerShip;
    }

    /**
     * Precursor Tech Abilities
     */

    /**
     * Jump Drive: Can travel anywhere without gates
     */
    public function canJumpTo(int $x, int $y): bool
    {
        // Precursor ship can jump anywhere in the galaxy
        return $this->precursor_tech['jump_drive'] ?? false;
    }

    /**
     * Calculate jump fuel cost (always 0 - infinite fuel)
     */
    public function getJumpFuelCost(int $targetX, int $targetY): int
    {
        return 0; // Infinite fuel
    }

    /**
     * Pocket dimension never fills
     */
    public function hasCargoSpace(int $amount): bool
    {
        return true; // Pocket dimension is effectively infinite
    }

    /**
     * Self-repair capability
     */
    public function repair(int $amount): void
    {
        $this->hull = min($this->max_hull, $this->hull + $amount);
        $this->save();
    }

    /**
     * Auto-repair every game cycle (matter replicator)
     */
    public function autoRepair(): void
    {
        if ($this->hull < $this->max_hull) {
            $repairAmount = (int) ($this->max_hull * 0.10); // 10% per cycle
            $this->repair($repairAmount);
        }
    }

    /**
     * Get detection radius (how far sensors can see)
     */
    public function getDetectionRadius(): int
    {
        return $this->sensors * 10; // 100 sensors = 1000 unit range
    }

    /**
     * Get combat power rating
     */
    public function getCombatRating(): int
    {
        return (int) (
            ($this->weapons * 0.4) +
            ($this->hull / 1000 * 0.3) +
            ($this->speed * 0.2) +
            ($this->sensors * 0.1)
        );
    }

    /**
     * Get ship display name
     */
    public function getDisplayName(): string
    {
        return $this->precursor_name.' (Precursor Vessel)';
    }

    /**
     * Check if ship has specific Precursor tech
     */
    public function hasTech(string $techName): bool
    {
        return isset($this->precursor_tech[$techName]) && $this->precursor_tech[$techName];
    }

    /**
     * Get age of the ship
     */
    public function getAge(): string
    {
        return '500,000 years';
    }

    /**
     * Get lore snippet for discovery
     */
    public function getDiscoveryLore(): string
    {
        return <<<'LORE'
Your sensors detect something impossible.

A ship. Impossibly old. Impossibly advanced.

Its hull material predates human civilization by half a million years.
Its power core still burns with energies we cannot name.

The Precursors left one vessel behind.

And you just found it.
LORE;
    }
}
