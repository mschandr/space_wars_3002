<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ColonyShipProduction extends Model
{
    protected $table = 'colony_ship_production';

    protected $fillable = [
        'uuid',
        'colony_id',
        'ship_id',
        'player_id',
        'production_progress',
        'production_cost_credits',
        'production_cost_minerals',
        'production_time_cycles',
        'status',
        'queue_position',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'production_progress' => 'integer',
        'production_cost_credits' => 'integer',
        'production_cost_minerals' => 'integer',
        'production_time_cycles' => 'integer',
        'queue_position' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($production) {
            if (empty($production->uuid)) {
                $production->uuid = Str::uuid();
            }
        });
    }

    /**
     * Get the colony this production belongs to
     */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /**
     * Get the ship type being produced
     */
    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    /**
     * Get the player ordering the production
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * Advance production progress
     */
    public function advanceProduction(int $amount): void
    {
        $this->production_progress = min(100, $this->production_progress + $amount);

        if ($this->production_progress >= 100) {
            $this->completeProduction();
        } else {
            $this->save();
        }
    }

    /**
     * Complete production and create the player ship
     */
    public function completeProduction(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();

        // Create the player ship
        PlayerShip::create([
            'uuid' => Str::uuid(),
            'player_id' => $this->player_id,
            'ship_id' => $this->ship_id,
            'name' => $this->ship->name . ' ' . rand(100, 999),
            'current_fuel' => $this->ship->attributes['max_fuel'] ?? 100,
            'max_fuel' => $this->ship->attributes['max_fuel'] ?? 100,
            'fuel_last_updated_at' => now(),
            'hull' => $this->ship->hull_strength,
            'max_hull' => $this->ship->hull_strength,
            'weapons' => $this->ship->attributes['starting_weapons'] ?? 10,
            'cargo_hold' => $this->ship->cargo_capacity,
            'sensors' => $this->ship->attributes['starting_sensors'] ?? 1,
            'warp_drive' => $this->ship->attributes['starting_warp_drive'] ?? 1,
            'current_cargo' => 0,
            'is_active' => false, // Player can activate it later
            'status' => 'operational',
        ]);

        // Advance queue
        $this->colony->shipProduction()
            ->where('queue_position', '>', $this->queue_position)
            ->decrement('queue_position');
    }

    /**
     * Cancel production
     */
    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->save();

        // Refund partial credits
        $refundAmount = (int) ($this->production_cost_credits * ($this->production_progress / 100) * 0.5);

        $player = $this->player;
        $player->credits += $refundAmount;
        $player->save();

        // Advance queue
        $this->colony->shipProduction()
            ->where('queue_position', '>', $this->queue_position)
            ->decrement('queue_position');
    }

    /**
     * Get status display
     */
    public function getStatusDisplay(): string
    {
        return match($this->status) {
            'queued' => '⏸️ Queued',
            'building' => '🏗️ Building',
            'completed' => '✅ Completed',
            'cancelled' => '❌ Cancelled',
            default => $this->status,
        };
    }

    /**
     * Calculate production costs for a ship
     */
    public static function calculateProductionCosts(Ship $ship): array
    {
        // Production costs are roughly 80% of base price
        $creditCost = (int) ($ship->base_price * 0.8);

        // Mineral cost scales with ship complexity
        $mineralCost = (int) ($ship->hull_strength * 10);

        // Larger/more complex ships take longer
        $timeCycles = max(5, (int) ($ship->base_price / 10000));

        return [
            'credits' => $creditCost,
            'minerals' => $mineralCost,
            'cycles' => $timeCycles,
        ];
    }

    /**
     * Start building if this is next in queue
     */
    public function startIfReady(): void
    {
        if ($this->queue_position === 1 && $this->status === 'queued') {
            $this->status = 'building';
            $this->started_at = now();
            $this->save();
        }
    }
}
