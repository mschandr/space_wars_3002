<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PirateCargo extends Model
{
    protected $table = 'pirate_cargo';

    protected $fillable = [
        'pirate_fleet_id',
        'mineral_id',
        'plan_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relationships
    public function pirateFleet(): BelongsTo
    {
        return $this->belongsTo(PirateFleet::class);
    }

    public function mineral(): BelongsTo
    {
        return $this->belongsTo(Mineral::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    // Helpers
    public function isMineralCargo(): bool
    {
        return $this->mineral_id !== null;
    }

    public function isPlanCargo(): bool
    {
        return $this->plan_id !== null;
    }
}
