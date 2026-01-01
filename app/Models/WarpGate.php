<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'distance' => 'float',
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

        if (!$source || !$destination) {
            return 0.0;
        }

        $dx = $destination->x - $source->x;
        $dy = $destination->y - $source->y;

        return sqrt($dx * $dx + $dy * $dy);
    }

    public function isAccessible(): bool
    {
        return !$this->is_hidden && $this->status === 'active';
    }
}
