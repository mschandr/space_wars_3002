<?php

namespace App\Models;

use App\Enums\WarpGate\GateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class WarpGate extends Model
{
    protected $fillable = [
        'uuid',
        'galaxy_id',
        'source_poi_id',
        'destination_poi_id',
        'is_hidden',
        'distance',
        'status',
        'gate_type',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'distance' => 'float',
        'gate_type' => GateType::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gate) {
            if (empty($gate->uuid)) {
                $gate->uuid = Str::uuid();
            }
        });
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
}
