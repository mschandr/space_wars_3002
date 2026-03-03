<?php

namespace App\Models;

use App\Enums\Economy\CommodityCategory;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commodity extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'category',
        'base_price',
        'is_conserved',
        'price_min_multiplier',
        'price_max_multiplier',
    ];

    protected $casts = [
        'category' => CommodityCategory::class,
        'is_conserved' => 'boolean',
        'base_price' => 'decimal:2',
        'price_min_multiplier' => 'decimal:2',
        'price_max_multiplier' => 'decimal:2',
    ];

    /**
     * Get ledger entries for this commodity
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CommodityLedgerEntry::class);
    }

    /**
     * Get resource deposits
     */
    public function deposits(): HasMany
    {
        return $this->hasMany(ResourceDeposit::class);
    }

    /**
     * Get economic shocks
     */
    public function shocks(): HasMany
    {
        return $this->hasMany(EconomicShock::class);
    }

    /**
     * Get hub stats for this commodity
     */
    public function hubStats(): HasMany
    {
        return $this->hasMany(HubCommodityStats::class);
    }

    /**
     * Scope: only conserved commodities
     */
    public function scopeConserved($query)
    {
        return $query->where('is_conserved', true);
    }

    /**
     * Scope: by category
     */
    public function scopeInCategory($query, CommodityCategory $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Find by code
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }
}
