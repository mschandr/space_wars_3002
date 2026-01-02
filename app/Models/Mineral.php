<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Enums\Trading\MineralRarity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Mineral extends Model
{
    use HasFactory;
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($mineral) {
            if (empty($mineral->uuid)) {
                $mineral->uuid = Str::uuid();
            }
        });
    }

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
