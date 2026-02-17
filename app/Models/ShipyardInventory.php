<?php

namespace App\Models;

use App\Enums\RarityTier;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipyardInventory extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'shipyard_inventory';

    protected $fillable = [
        'uuid',
        'poi_id',
        'ship_id',
        'name',
        'rarity',
        'price',
        'hull_strength',
        'shield_strength',
        'cargo_capacity',
        'speed',
        'weapon_slots',
        'utility_slots',
        'max_fuel',
        'sensors',
        'warp_drive',
        'weapons',
        'variation_traits',
        'attributes',
        'is_sold',
    ];

    protected $casts = [
        'rarity' => RarityTier::class,
        'price' => 'decimal:2',
        'hull_strength' => 'integer',
        'shield_strength' => 'integer',
        'cargo_capacity' => 'integer',
        'speed' => 'integer',
        'weapon_slots' => 'integer',
        'utility_slots' => 'integer',
        'max_fuel' => 'integer',
        'sensors' => 'integer',
        'warp_drive' => 'integer',
        'weapons' => 'integer',
        'variation_traits' => 'array',
        'attributes' => 'array',
        'is_sold' => 'boolean',
    ];

    public function shipyard(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    public function ship(): BelongsTo
    {
        return $this->belongsTo(Ship::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_sold', false);
    }
}
