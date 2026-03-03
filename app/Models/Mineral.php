<?php

namespace App\Models;

use App\Enums\Trading\CommodityCategory;
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
        'category',
        'is_illegal',
        'min_reputation',
        'min_sector_security',
        'attributes',
    ];

    protected $casts = [
        'base_value' => 'decimal:2',
        'rarity' => MineralRarity::class,
        'category' => CommodityCategory::class,
        'is_illegal' => 'boolean',
        'min_reputation' => 'integer',
        'min_sector_security' => 'integer',
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
