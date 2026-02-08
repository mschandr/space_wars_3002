<?php

namespace App\Models;

use App\Enums\PointsOfInterest\PointOfInterestType;
use Illuminate\Database\Eloquent\Model;

class PoiType extends Model
{
    protected $table = 'poi_types';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'label',
        'description',
        'domain',
        'is_habitable',
        'is_mineable',
        'is_orbital',
        'is_dockable',
        'can_have_trading_hub',
        'can_have_warp_gate',
        'base_danger_level',
        'icon',
        'color',
        'category',
        'produces_minerals',
    ];

    protected $casts = [
        'is_habitable' => 'boolean',
        'is_mineable' => 'boolean',
        'is_orbital' => 'boolean',
        'is_dockable' => 'boolean',
        'can_have_trading_hub' => 'boolean',
        'can_have_warp_gate' => 'boolean',
        'base_danger_level' => 'integer',
        'produces_minerals' => 'array',
    ];

    /**
     * Get the corresponding enum value.
     */
    public function toEnum(): PointOfInterestType
    {
        return PointOfInterestType::from($this->id);
    }

    /**
     * Find by enum.
     */
    public static function fromEnum(PointOfInterestType $type): ?self
    {
        return static::find($type->value);
    }

    /**
     * Find by code (e.g., 'STAR', 'PLANET').
     */
    public static function byCode(string $code): ?self
    {
        return static::where('code', strtoupper($code))->first();
    }

    /**
     * Scope to get only habitable types.
     */
    public function scopeHabitable($query)
    {
        return $query->where('is_habitable', true);
    }

    /**
     * Scope to get only mineable types.
     */
    public function scopeMineable($query)
    {
        return $query->where('is_mineable', true);
    }

    /**
     * Scope to get types by domain.
     */
    public function scopeInDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * Scope to get types by category.
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if this type produces a specific mineral.
     */
    public function producesMineral(string $mineralSymbol): bool
    {
        return in_array($mineralSymbol, $this->produces_minerals ?? []);
    }
}
