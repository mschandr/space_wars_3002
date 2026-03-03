<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomsOfficial extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'poi_id',
        'name',
        'honesty',
        'severity',
        'bribe_threshold',
        'detection_skill',
    ];

    protected $casts = [
        'honesty' => 'decimal:2',
        'severity' => 'decimal:2',
        'bribe_threshold' => 'integer',
        'detection_skill' => 'decimal:2',
    ];

    /**
     * Get the POI where this official works
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Check if this official can be bribed
     * (honesty < 0.7 means they're open to it)
     */
    public function canBeBribed(): bool
    {
        return (float) $this->honesty < 0.7;
    }

    /**
     * Check if this official is very strict
     * (severity > 0.8 means maximum enforcement)
     */
    public function isVeryStrict(): bool
    {
        return (float) $this->severity > 0.8;
    }
}
