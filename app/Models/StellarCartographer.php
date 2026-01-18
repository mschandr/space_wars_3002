<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StellarCartographer extends Model
{
    use HasFactory;

    protected $fillable = [
        'poi_id',
        'name',
        'is_active',
        'chart_base_price',
        'markup_multiplier',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'chart_base_price' => 'decimal:2',
        'markup_multiplier' => 'decimal:2',
    ];

    /**
     * The POI (location) where this cartographer is located
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }
}
