<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks which warp lanes (gates) a player has discovered.
 *
 * This enables fog of war for warp lanes - players only see lanes they've
 * traveled, scanned, or learned about through star charts.
 */
class PilotLaneKnowledge extends Model
{
    use HasFactory;

    protected $table = 'pilot_lane_knowledge';

    protected $fillable = [
        'player_id',
        'warp_gate_id',
        'discovered_at',
        'discovery_method',
        'pirate_risk_known',
        'last_pirate_check',
    ];

    protected $casts = [
        'discovered_at' => 'datetime',
        'pirate_risk_known' => 'boolean',
        'last_pirate_check' => 'datetime',
    ];

    /**
     * Valid discovery methods.
     */
    public const DISCOVERY_METHODS = [
        'travel',   // Discovered by traveling through the gate
        'scan',     // Discovered by scanning the system
        'chart',    // Learned from a purchased star chart
        'intel',    // Acquired through intel or other means
        'spawn',    // Auto-discovered at spawn location
    ];

    /**
     * The player who discovered this lane.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * The warp gate that was discovered.
     */
    public function warpGate(): BelongsTo
    {
        return $this->belongsTo(WarpGate::class);
    }

    /**
     * Check if pirate risk information is stale.
     *
     * @param  int  $maxAgeMinutes  Maximum age in minutes before considered stale
     */
    public function isPirateRiskStale(int $maxAgeMinutes = 60): bool
    {
        if (! $this->pirate_risk_known || ! $this->last_pirate_check) {
            return true;
        }

        return $this->last_pirate_check->diffInMinutes(now()) > $maxAgeMinutes;
    }

    /**
     * Mark pirate risk as known with current timestamp.
     */
    public function markPirateRiskKnown(): void
    {
        $this->pirate_risk_known = true;
        $this->last_pirate_check = now();
        $this->save();
    }
}
