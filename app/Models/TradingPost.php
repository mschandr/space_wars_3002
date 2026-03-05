<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * TradingPost represents a predefined vendor template
 *
 * Each trading post has a name, trait profile, and dialogue pool.
 * Vendor instances are created from trading post templates, one per POI per galaxy.
 */
class TradingPost extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'uuid',
        'name',
        'service_type',      // 'trading_hub', 'salvage_yard', 'shipyard', 'market'
        'base_criminality',  // Base criminality for this trading post (0.0-1.0)
        'personality',       // Base personality traits
        'dialogue_pool',
        'markup_base',
    ];

    protected $casts = [
        'base_criminality' => 'decimal:2',
        'personality' => 'array',
        'dialogue_pool' => 'array',
        'markup_base' => 'decimal:4',
    ];

    /**
     * Get all vendor instances created from this trading post
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(VendorProfile::class);
    }

    /**
     * Get a specific personality trait (0.0-1.0)
     */
    public function getPersonality(string $traitName): float
    {
        $personality = $this->personality ?? [];
        return $personality[$traitName] ?? 0.5;
    }

    /**
     * Get dialogue for a specific context
     */
    public function getDialogue(string $context): string
    {
        $pool = $this->dialogue_pool ?? [];
        $lines = $pool[$context] ?? [];

        if (empty($lines)) {
            return match ($context) {
                'greeting' => "Welcome to {$this->name}.",
                'deal_accepted' => 'Pleasure doing business with you.',
                'deal_refused' => 'Your loss.',
                'farewell' => 'Come again soon.',
                'lockout' => 'I have nothing for you.',
                default => 'Hmm?',
            };
        }

        return $lines[array_rand($lines)];
    }
}
