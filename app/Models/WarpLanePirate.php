<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarpLanePirate extends Model
{
    protected $fillable = [
        'uuid',
        'warp_gate_id',
        'captain_id',
        'fleet_size',
        'difficulty_tier',
        'is_active',
        'last_encounter_at',
    ];

    protected $casts = [
        'fleet_size' => 'integer',
        'difficulty_tier' => 'integer',
        'is_active' => 'boolean',
        'last_encounter_at' => 'datetime',
    ];

    // Relationships
    public function warpGate(): BelongsTo
    {
        return $this->belongsTo(WarpGate::class);
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(PirateCaptain::class);
    }

    // Helpers
    public function recordEncounter(): void
    {
        $this->last_encounter_at = now();
        $this->save();
    }
}
