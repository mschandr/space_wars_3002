<?php

namespace App\Models;

use App\Enums\Trading\MineralRarity;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mineral extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'symbol',
        'description',
        'base_value',
        'rarity',
        'attributes',
    ];

    protected $casts = [
        'base_value' => 'decimal:2',
        'rarity' => MineralRarity::class,
        'attributes' => 'array',
    ];

    /**
     * Get all trading hub inventories for this mineral
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(TradingHubInventory::class);
    }

    /**
     * Calculate the current market value based on rarity
     */
    public function getMarketValue(): float
    {
        $rarityMultiplier = $this->rarity->valueMultiplier();

        return $this->base_value * $rarityMultiplier;
    }
}
