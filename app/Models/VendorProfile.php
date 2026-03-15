<?php

namespace App\Models;

use App\Enums\Vendor\VendorArchetype;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProfile extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'archetype',
        'service_type',
        'criminality',
        'personality',
        'markup_base',
    ];

    protected $casts = [
        'archetype' => VendorArchetype::class,
        'criminality' => 'decimal:2',
        'personality' => 'array',
        'markup_base' => 'decimal:4',
    ];

    /**
     * Get galaxy instances of this vendor profile template
     */
    public function galaxyInstances(): HasMany
    {
        return $this->hasMany(GalaxyVendorProfile::class);
    }

    /**
     * Get player relationships for this vendor
     */
    public function playerRelationships(): HasMany
    {
        return $this->hasMany(PlayerVendorRelationship::class);
    }

    /**
     * Check if this vendor is a black market dealer
     */
    public function isBlackMarketDealer(): bool
    {
        return (float) $this->criminality >= 0.8;
    }

    /**
     * Get a specific personality trait (0.0-1.0)
     */
    public function getPersonality(string $traitName): float
    {
        $personality = $this->personality ?? [];
        return $personality[$traitName] ?? 0.5;  // Default to neutral
    }

}
