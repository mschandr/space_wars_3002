<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProfile extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'galaxy_id',
        'poi_id',
        'trading_post_id',
        'service_type',      // 'trading_hub', 'salvage_yard', 'shipyard', 'market'
        'criminality',        // 0.0-1.0, high = black market dealer
        'personality',
        'dialogue_pool',
        'markup_base',
    ];

    protected $casts = [
        'criminality' => 'decimal:2',
        'personality' => 'array',
        'dialogue_pool' => 'array',
        'markup_base' => 'decimal:4',
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
     * Get the trading post template this vendor is based on
     */
    public function tradingPost(): BelongsTo
    {
        return $this->belongsTo(TradingPost::class);
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

    /**
     * Get dialogue for a specific context
     * Returns a random line from the pool for that context, or fallback
     */
    public function getDialogue(string $context): string
    {
        $pool = $this->dialogue_pool ?? [];
        $lines = $pool[$context] ?? [];

        if (empty($lines)) {
            return $this->getDefaultDialogue($context);
        }

        return $lines[array_rand($lines)];
    }

    /**
     * Get default dialogue for common contexts
     */
    private function getDefaultDialogue(string $context): string
    {
        $name = $this->tradingPost?->name ?? 'Merchant';

        return match ($context) {
            'greeting' => "Welcome to {$name}.",
            'deal_accepted' => 'Pleasure doing business with you.',
            'deal_refused' => 'Your loss.',
            'farewell' => 'Come again soon.',
            'lockout' => 'I have nothing for you.',
            default => 'Hmm?',
        };
    }
}
