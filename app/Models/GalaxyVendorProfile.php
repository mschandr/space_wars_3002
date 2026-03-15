<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GalaxyVendorProfile extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'poi_id',
        'vendor_profile_id',
        'trading_post_id',
        'service_type',
        'criminality',
        'dialogue_generation_status',
        'dialogue_generation_version',
        'dialogue_generated_at',
    ];

    protected $casts = [
        'criminality' => 'decimal:2',
        'dialogue_generation_status' => 'string',
        'dialogue_generation_version' => 'integer',
        'dialogue_generated_at' => 'datetime',
    ];

    /**
     * Get the galaxy this vendor belongs to
     */
    public function galaxy(): BelongsTo
    {
        return $this->belongsTo(Galaxy::class);
    }

    /**
     * Get the POI where this vendor operates
     */
    public function pointOfInterest(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'poi_id');
    }

    /**
     * Get the vendor profile template this instance is based on
     */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    /**
     * Get the trading post template this vendor is based on
     */
    public function tradingPost(): BelongsTo
    {
        return $this->belongsTo(TradingPost::class);
    }

    /**
     * Get dialogue lines for this vendor instance
     */
    public function dialogueLines(): HasMany
    {
        return $this->hasMany(VendorDialogue::class, 'galaxy_vendor_profile_id');
    }

    /**
     * Check if this vendor is a black market dealer
     */
    public function isBlackMarketDealer(): bool
    {
        return (float) $this->criminality >= 0.8;
    }

    /**
     * Scope: Find vendors needing dialogue generation.
     */
    public function scopeNeedingDialogueGeneration($query)
    {
        return $query->whereIn('dialogue_generation_status', ['pending', 'failed']);
    }

    /**
     * Mark dialogue as stale and schedule regeneration.
     */
    public function markDialogueStale(): void
    {
        $this->update([
            'dialogue_generation_status' => 'pending',
            'dialogue_generation_version' => $this->dialogue_generation_version + 1,
            'dialogue_generated_at' => null,
        ]);
    }

    /**
     * Get personality traits from the template vendor profile
     */
    public function getPersonality(string $traitName): float
    {
        $personality = $this->vendorProfile?->personality ?? [];
        return $personality[$traitName] ?? 0.5;  // Default to neutral
    }

}
