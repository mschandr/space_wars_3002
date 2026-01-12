<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ColonyMission extends Model
{
    protected $fillable = [
        'uuid',
        'colony_id',
        'player_ship_id',
        'destination_poi_id',
        'mission_type',
        'colonists_aboard',
        'cargo_capacity_used',
        'cargo_manifest',
        'status',
        'turns_remaining',
        'launched_at',
        'arrival_at',
        'completed_at',
    ];

    protected $casts = [
        'colonists_aboard' => 'integer',
        'cargo_capacity_used' => 'integer',
        'cargo_manifest' => 'array',
        'turns_remaining' => 'integer',
        'launched_at' => 'datetime',
        'arrival_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($mission) {
            if (empty($mission->uuid)) {
                $mission->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the colony this mission originated from
     */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /**
     * Get the ship on this mission
     */
    public function playerShip(): BelongsTo
    {
        return $this->belongsTo(PlayerShip::class);
    }

    /**
     * Get the destination POI
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'destination_poi_id');
    }

    /**
     * Launch the mission
     */
    public function launch(): void
    {
        $this->status = 'in_transit';
        $this->launched_at = now();

        // Calculate travel time based on distance
        $colony = $this->colony;
        $destination = $this->destination;

        $distance = sqrt(
            pow($destination->x - $colony->poi->x, 2) +
            pow($destination->y - $colony->poi->y, 2)
        );

        // Travel time in turns (1 turn per 10 units of distance)
        $this->turns_remaining = max(1, (int) ceil($distance / 10));
        $this->arrival_at = now()->addHours($this->turns_remaining);

        $this->save();
    }

    /**
     * Advance mission by one turn
     */
    public function advanceTurn(): void
    {
        if ($this->status !== 'in_transit') {
            return;
        }

        $this->turns_remaining = max(0, $this->turns_remaining - 1);

        if ($this->turns_remaining === 0) {
            $this->arrive();
        } else {
            $this->save();
        }
    }

    /**
     * Handle arrival at destination
     */
    public function arrive(): void
    {
        $this->status = 'arrived';
        $this->arrival_at = now();
        $this->save();

        // Process mission based on type
        match ($this->mission_type) {
            'colonize' => $this->processColonization(),
            'trade_route' => $this->processTradeRoute(),
            'defend' => $this->processDefense(),
            'explore' => $this->processExploration(),
            default => null,
        };
    }

    /**
     * Process colonization mission
     */
    private function processColonization(): void
    {
        $destination = $this->destination;

        // Check if destination is colonizable
        if (! $destination->is_colonizable || $destination->is_colonized) {
            $this->status = 'failed';
            $this->completed_at = now();
            $this->save();

            return;
        }

        // Create new colony
        $colony = Colony::create([
            'uuid' => Str::uuid(),
            'player_id' => $this->colony->player_id,
            'poi_id' => $destination->id,
            'name' => $destination->name.' Colony',
            'population' => $this->colonists_aboard,
            'habitability_rating' => $destination->habitability_score,
            'established_at' => now(),
        ]);

        // Mark destination as colonized
        $destination->is_colonized = true;
        $destination->save();

        // Transfer cargo to new colony
        if (! empty($this->cargo_manifest)) {
            // Logic to transfer minerals and resources
        }

        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Process trade route mission
     */
    private function processTradeRoute(): void
    {
        // Future implementation for trade routes
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Process defense mission
     */
    private function processDefense(): void
    {
        // Future implementation for defense
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Process exploration mission
     */
    private function processExploration(): void
    {
        // Future implementation for exploration
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Get mission type display
     */
    public function getMissionTypeDisplay(): string
    {
        return match ($this->mission_type) {
            'colonize' => '🌍 Colonization',
            'trade_route' => '🚚 Trade Route',
            'defend' => '🛡️ Defense',
            'explore' => '🔭 Exploration',
            default => ucfirst($this->mission_type),
        };
    }

    /**
     * Get status display
     */
    public function getStatusDisplay(): string
    {
        return match ($this->status) {
            'preparing' => '⏳ Preparing',
            'in_transit' => '🚀 In Transit',
            'arrived' => '📍 Arrived',
            'completed' => '✅ Completed',
            'failed' => '❌ Failed',
            default => $this->status,
        };
    }
}
